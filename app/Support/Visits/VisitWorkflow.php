<?php

namespace App\Support\Visits;

use App\Models\CustomerVisit;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Notifications\InternalNotifier;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;

class VisitWorkflow
{
    public function __construct(
        private AuditLogger $audit,
        private InternalNotifier $notifier,
    ) {}

    /**
     * @param  array{
     *     customer_id: int,
     *     assigned_user_id: int,
     *     scheduled_at: string,
     *     notes?: string|null
     * }  $data
     */
    public function schedule(User $actor, array $data): CustomerVisit
    {
        return DB::transaction(function () use ($actor, $data): CustomerVisit {
            $visit = CustomerVisit::query()->create([
                'customer_id' => $data['customer_id'],
                'assigned_user_id' => $data['assigned_user_id'],
                'created_by_user_id' => $actor->id,
                'scheduled_at' => $data['scheduled_at'],
                'status' => VisitStatus::Scheduled,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->audit->record('visit.scheduled', $visit, [
                'customer_id' => $visit->customer_id,
                'assigned_user_id' => $visit->assigned_user_id,
                'scheduled_at' => $visit->scheduled_at?->timezone(config('app.timezone'))->toIso8601String(),
                'status' => $visit->status->value,
            ], $actor);

            $this->notifyAssigneeIfNeeded($visit, $actor, 'تم جدولة زيارة عميل لك.');

            return $visit->load(['customer', 'assignedUser']);
        });
    }

    /**
     * @param  array{scheduled_at: string, assigned_user_id: int, notes?: string|null}  $data
     */
    public function reschedule(CustomerVisit $visit, User $actor, array $data): CustomerVisit
    {
        $this->assertScheduled($visit);

        return DB::transaction(function () use ($visit, $actor, $data): CustomerVisit {
            $beforeScheduled = $visit->scheduled_at;

            $before = [
                'scheduled_at' => $beforeScheduled?->timezone(config('app.timezone'))->toIso8601String(),
                'assigned_user_id' => $visit->assigned_user_id,
                'notes' => $visit->notes,
            ];

            $visit->fill([
                'scheduled_at' => $data['scheduled_at'],
                'assigned_user_id' => $data['assigned_user_id'],
                'notes' => $data['notes'] ?? null,
            ])->save();

            $after = [
                'scheduled_at' => $visit->scheduled_at?->timezone(config('app.timezone'))->toIso8601String(),
                'assigned_user_id' => $visit->assigned_user_id,
                'notes' => $visit->notes,
            ];

            $this->audit->record('visit.rescheduled', $visit, [
                'customer_id' => $visit->customer_id,
                ...$this->audit->changes($before, $after, ['scheduled_at', 'assigned_user_id', 'notes']),
            ], $actor);

            if ($this->scheduledTimeChanged($beforeScheduled, $visit->scheduled_at)) {
                $this->notifyAssigneeIfNeeded($visit, $actor, 'أُعيدت جدولة موعد زيارة عميل لك.');
            }

            return $visit->fresh(['customer', 'assignedUser']);
        });
    }

    /**
     * @param  array{outcome: string, notes?: string|null, customer_needs?: string|null}  $data
     */
    public function complete(CustomerVisit $visit, User $actor, array $data): CustomerVisit
    {
        $this->assertScheduled($visit);

        return DB::transaction(function () use ($visit, $actor, $data): CustomerVisit {
            $visit->fill([
                'status' => VisitStatus::Completed,
                'outcome' => $data['outcome'],
                'notes' => $data['notes'] ?? $visit->notes,
                'customer_needs' => $data['customer_needs'] ?? null,
                'completed_at' => now(),
            ])->save();

            $this->audit->record('visit.completed', $visit, [
                'customer_id' => $visit->customer_id,
                'status' => $visit->status->value,
                'has_outcome' => filled($visit->outcome),
                'has_customer_needs' => filled($visit->customer_needs),
            ], $actor);

            return $visit->fresh(['customer', 'assignedUser']);
        });
    }

    public function cancel(CustomerVisit $visit, User $actor, ?string $notes = null): CustomerVisit
    {
        $this->assertScheduled($visit);

        return DB::transaction(function () use ($visit, $actor, $notes): CustomerVisit {
            $visit->fill([
                'status' => VisitStatus::Cancelled,
                'cancelled_at' => now(),
                'notes' => $notes ?? $visit->notes,
            ])->save();

            $this->audit->record('visit.cancelled', $visit, [
                'customer_id' => $visit->customer_id,
                'status' => $visit->status->value,
            ], $actor);

            $this->notifyAssigneeIfNeeded($visit, $actor, 'تم إلغاء زيارة عميل كانت مُعيَّنة لك.');

            return $visit->fresh(['customer', 'assignedUser']);
        });
    }

    private function assertScheduled(CustomerVisit $visit): void
    {
        if ($visit->status !== VisitStatus::Scheduled) {
            throw new DomainException('لا يمكن تعديل زيارة غير مجدولة.');
        }
    }

    private function scheduledTimeChanged(?Carbon $before, ?Carbon $after): bool
    {
        if ($before === null || $after === null) {
            return $before !== $after;
        }

        // Compare at minute precision (datetime-local UI has no seconds).
        $tz = config('app.timezone');

        return $before->timezone($tz)->format('Y-m-d H:i')
            !== $after->timezone($tz)->format('Y-m-d H:i');
    }

    private function notifyAssigneeIfNeeded(CustomerVisit $visit, User $actor, string $title): void
    {
        if ((int) $visit->assigned_user_id === (int) $actor->id) {
            return;
        }

        $assignee = $visit->assignedUser ?? User::query()->find($visit->assigned_user_id);
        if ($assignee === null) {
            return;
        }

        $customerName = $visit->customer?->name_ar ?? '#'.$visit->customer_id;

        $this->notifier->notifyUser($assignee, [
            'title_ar' => $title,
            'body_ar' => 'العميل: '.$customerName,
            'action_url' => route('visits.show', $visit, false),
            'category' => 'visits',
        ]);
    }
}
