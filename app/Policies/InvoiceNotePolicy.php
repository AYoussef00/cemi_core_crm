<?php

namespace App\Policies;

use App\Models\InvoiceNote;
use App\Models\User;
use App\Support\Finance\InvoiceNoteStatus;

class InvoiceNotePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('credit_notes.create')
            || $user->can('credit_notes.approve')
            || $user->can('invoices.view');
    }

    public function view(User $user, InvoiceNote $note): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('credit_notes.create');
    }

    public function submit(User $user, InvoiceNote $note): bool
    {
        return $user->can('credit_notes.create') && $note->status === InvoiceNoteStatus::Draft;
    }

    public function decide(User $user, InvoiceNote $note): bool
    {
        if (! $user->can('credit_notes.approve') || $note->status !== InvoiceNoteStatus::PendingApproval) {
            return false;
        }

        return (int) $note->created_by_user_id !== (int) $user->id;
    }
}
