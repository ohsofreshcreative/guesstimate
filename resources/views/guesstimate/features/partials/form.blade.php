@php
  $errors ??= [];
  $isOld = isset($old) && ($openModal ?? null) === $modalId;
  $val = fn ($key, $default = null) => $isOld ? ($old[$key] ?? null) : ($feature?->{$key} ?? $default);
  $err = fn ($key) => $isOld ? ($errors[$key] ?? null) : null;
@endphp

<div class="grid gap-4 sm:grid-cols-2">
  <x-gst.field label="Nazwa" :for="$modalId . '-name'" :error="$err('name')" required class="sm:col-span-2">
    <x-gst.input name="name" :id="$modalId . '-name'" :value="$val('name')" :error="$err('name')" maxlength="190" />
  </x-gst.field>
  <x-gst.field label="Kategoria" :for="$modalId . '-category'" :error="$err('category_id')" required>
    <x-gst.select name="category_id" :id="$modalId . '-category'" :options="$categoryOptions" :value="$val('category_id', $defaultCategory ?? null)" placeholder="— wybierz —" :error="$err('category_id')" />
  </x-gst.field>
  <x-gst.field label="Etap projektu" :for="$modalId . '-stage'" :error="$err('stage_key')">
    <x-gst.select name="stage_key" :id="$modalId . '-stage'" :options="$stages" :value="$val('stage_key', 'dev')" />
  </x-gst.field>
  <x-gst.field label="Godziny minimum" :for="$modalId . '-min'" :error="$err('hours_min')" required>
    <x-gst.input name="hours_min" :id="$modalId . '-min'" :value="$isOld ? $val('hours_min') : \App\Guesstimate\Support\Format::hoursInput($feature?->hours_min)" :error="$err('hours_min')" suffix="h" inputmode="decimal" />
  </x-gst.field>
  <x-gst.field label="Godziny maksimum" :for="$modalId . '-max'" :error="$err('hours_max')" required>
    <x-gst.input name="hours_max" :id="$modalId . '-max'" :value="$isOld ? $val('hours_max') : \App\Guesstimate\Support\Format::hoursInput($feature?->hours_max)" :error="$err('hours_max')" suffix="h" inputmode="decimal" />
  </x-gst.field>
  <x-gst.field label="Opis" :for="$modalId . '-description'" class="sm:col-span-2">
    <x-gst.textarea name="description" :id="$modalId . '-description'" :value="$val('description')" rows="3" />
  </x-gst.field>
  <div class="sm:col-span-2">
    <x-gst.checkbox name="is_active" label="Aktywna" hint="Nieaktywne funkcjonalności nie pojawiają się w kreatorze ofert." :checked="$isOld ? ! empty($old['is_active']) : ($feature?->is_active ?? true)" />
  </div>
</div>
