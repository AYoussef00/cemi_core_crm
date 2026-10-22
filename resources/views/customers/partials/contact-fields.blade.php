@php
    /** @var list<\App\Support\Customers\CustomerContactRole> $contactRoles */
    /** @var \Illuminate\Support\Collection|\Illuminate\Database\Eloquent\Collection|array $existing */
    $existing = $existing ?? collect();
    $rows = old('contacts');
    if (! is_array($rows)) {
        $rows = collect($existing)->map(fn ($c) => [
            'role' => $c->role instanceof \BackedEnum ? $c->role->value : (string) $c->role,
            'name_ar' => $c->name_ar,
            'phone' => $c->phone,
            'email' => $c->email,
        ])->values()->all();
    }

    $byRole = [];
    foreach ($rows as $row) {
        $roleKey = (string) ($row['role'] ?? '');
        if ($roleKey !== '' && ! isset($byRole[$roleKey])) {
            $byRole[$roleKey] = $row;
        }
    }
@endphp

<fieldset class="customers-contacts">
    <legend class="customers-contacts-legend">جهات الاتصال</legend>
    <p class="customers-contacts-hint">
        جهة الاتصال التجارية وجهة اتصال المحاسبة. يمكنك ترك أي جهة فارغة وإكمالها لاحقًا.
    </p>

    <div class="customers-contacts-list">
        @foreach ($contactRoles as $index => $role)
            @php
                $row = $byRole[$role->value] ?? ['role' => $role->value, 'name_ar' => '', 'phone' => '', 'email' => ''];
                $roleClass = $role === \App\Support\Customers\CustomerContactRole::Accounting
                    ? 'is-accounting'
                    : 'is-account';
            @endphp
            <div class="customers-contact-row {{ $roleClass }}">
                <div class="customers-contact-row-head">
                    <span class="customers-contact-role">{{ $role->labelAr() }}</span>
                </div>
                <input type="hidden" name="contacts[{{ $index }}][role]" value="{{ $role->value }}">

                <div class="customers-contact-fields">
                    <div class="auth-field">
                        <label for="contact-name-{{ $index }}">الاسم</label>
                        <input id="contact-name-{{ $index }}" type="text" name="contacts[{{ $index }}][name_ar]"
                               value="{{ $row['name_ar'] ?? '' }}" maxlength="255"
                               aria-invalid="{{ $errors->has('contacts.'.$index.'.name_ar') ? 'true' : 'false' }}">
                        @error('contacts.'.$index.'.name_ar')
                            <p class="auth-error" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="auth-field">
                        <label for="contact-phone-{{ $index }}">الهاتف</label>
                        <input id="contact-phone-{{ $index }}" type="text" name="contacts[{{ $index }}][phone]"
                               value="{{ $row['phone'] ?? '' }}" maxlength="64" class="dir-ltr"
                               inputmode="tel"
                               aria-invalid="{{ $errors->has('contacts.'.$index.'.phone') ? 'true' : 'false' }}">
                        @error('contacts.'.$index.'.phone')
                            <p class="auth-error" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="auth-field">
                        <label for="contact-email-{{ $index }}">البريد</label>
                        <input id="contact-email-{{ $index }}" type="email" name="contacts[{{ $index }}][email]"
                               value="{{ $row['email'] ?? '' }}" maxlength="255" class="dir-ltr" autocomplete="off"
                               aria-invalid="{{ $errors->has('contacts.'.$index.'.email') ? 'true' : 'false' }}">
                        @error('contacts.'.$index.'.email')
                            <p class="auth-error" role="alert">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</fieldset>
