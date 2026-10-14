{{-- Pola klienta. $client (opcjonalnie), $errors, $old. --}}
@php
  $errors ??= [];
  $val = fn ($key) => isset($old) ? ($old[$key] ?? null) : ($client?->{$key} ?? null);
@endphp

<div class="grid gap-4 sm:grid-cols-2">
  <x-gst.field label="Nazwa firmy" :for="$prefix . 'company_name'" :error="$errors['company_name'] ?? null" required class="sm:col-span-2">
    <x-gst.input name="company_name" :id="$prefix . 'company_name'" :value="$val('company_name')" :error="$errors['company_name'] ?? null" maxlength="190" />
  </x-gst.field>
  <x-gst.field label="Adres strony WWW" :for="$prefix . 'website_url'" :error="$errors['website_url'] ?? null" class="sm:col-span-2">
    <x-gst.input name="website_url" :id="$prefix . 'website_url'" :value="$val('website_url')" :error="$errors['website_url'] ?? null" placeholder="https://" />
  </x-gst.field>
  <x-gst.field label="Osoba kontaktowa" :for="$prefix . 'contact_name'" :error="$errors['contact_name'] ?? null">
    <x-gst.input name="contact_name" :id="$prefix . 'contact_name'" :value="$val('contact_name')" />
  </x-gst.field>
  <x-gst.field label="NIP" :for="$prefix . 'nip'" :error="$errors['nip'] ?? null" hint="Opcjonalnie">
    <x-gst.input name="nip" :id="$prefix . 'nip'" :value="$val('nip')" :error="$errors['nip'] ?? null" />
  </x-gst.field>
  <x-gst.field label="E-mail" :for="$prefix . 'email'" :error="$errors['email'] ?? null">
    <x-gst.input type="email" name="email" :id="$prefix . 'email'" :value="$val('email')" :error="$errors['email'] ?? null" />
  </x-gst.field>
  <x-gst.field label="Telefon" :for="$prefix . 'phone'" :error="$errors['phone'] ?? null">
    <x-gst.input type="tel" name="phone" :id="$prefix . 'phone'" :value="$val('phone')" :error="$errors['phone'] ?? null" />
  </x-gst.field>
  <x-gst.field label="Notatki" :for="$prefix . 'notes'" :error="$errors['notes'] ?? null" class="sm:col-span-2">
    <x-gst.textarea name="notes" :id="$prefix . 'notes'" :value="$val('notes')" rows="3" />
  </x-gst.field>
</div>
