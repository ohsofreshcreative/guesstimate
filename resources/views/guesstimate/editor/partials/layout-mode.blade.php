{{-- Tryb układu całego dokumentu i migracja tej wersji roboczej. Dotyczy tylko bieżącej wersji; publikacje zmienia dopiero publikacja. --}}
<div class="{{ $section }} space-y-2 text-sm" data-layout-mode>
  <h3 class="font-semibold">Tryb układu</h3>

  <template x-if="! migration">
    <div class="space-y-2">
      <label class="flex items-center gap-2"><input type="radio" name="gst-layout-mode" class="size-4 border-line-strong text-accent focus:ring-accent" :checked="! templateMode" x-on:change="setLayoutMode(false)" data-layout-mode-new>Nowy: pola w zapisanych pozycjach</label>
      <label class="flex items-center gap-2"><input type="radio" name="gst-layout-mode" class="size-4 border-line-strong text-accent focus:ring-accent" :checked="templateMode" x-on:change="setLayoutMode(true)" data-layout-mode-old>Stary: strony z szablonu</label>
      <p class="text-xs text-ink-muted" x-show="templateMode">Zapisane pozycje i kopie pól zostają, ale nie są pokazywane. Powrót do nowego trybu je przywróci.</p>
      <button type="button" class="{{ $secondary }}" x-on:click="startMigration()" data-migration-start>Przenieś tę ofertę do nowego układu</button>
      <p class="text-xs text-ink-muted">Przypina pola w obecnych miejscach. Najpierw zobaczysz podgląd. Inne oferty i opublikowane linki się nie zmienią.</p>
    </div>
  </template>

  <template x-if="migration">
    <div class="space-y-2 rounded-control border border-accent bg-accent-soft p-3" role="status" data-migration-preview>
      <p class="font-medium">Podgląd migracji: nic nie jest jeszcze zapisane</p>
      <p x-show="migration.moved.length" x-text="`Przeniesione strony: ${migration.moved.join(', ')}.`"></p>
      <p x-show="! migration.moved.length">Żadna strona nie wymaga przeniesienia.</p>
      <p class="text-danger" x-show="migration.skipped.length" x-text="`Bez zmian, bo treść nie mieści się na stronie: ${migration.skipped.join(', ')}.`" data-migration-skipped></p>
      <div class="flex gap-2">
        <button type="button" class="{{ $secondary }}" x-on:click="save()" data-migration-apply>Zapisz migrację</button>
        <button type="button" class="{{ $ghost }}" x-on:click="cancelMigration()" data-migration-cancel>Anuluj</button>
      </div>
    </div>
  </template>

  <p class="text-xs text-danger" x-show="fieldError(`document.pages.${selected}.fields`)" x-text="fieldError(`document.pages.${selected}.fields`)"></p>
  <p class="text-xs text-danger" x-show="fieldError(`document.pages.${selected}.polish`)" x-text="fieldError(`document.pages.${selected}.polish`)"></p>
  <p class="text-xs text-danger" x-show="fieldError(`document.pages.${selected}.image_crop`)" x-text="fieldError(`document.pages.${selected}.image_crop`)"></p>
  <p class="text-xs text-danger" x-show="fieldError('document.layout_mode')" x-text="fieldError('document.layout_mode')"></p>
</div>
