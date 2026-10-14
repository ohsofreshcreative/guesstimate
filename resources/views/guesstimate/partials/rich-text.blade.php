{{-- Edytor WYSIWYG powiązany z wyrażeniem Alpine $model. --}}
<div x-data="richText()" x-modelable="html" x-model="{{ $model }}" class="min-w-0">
  @include('guesstimate.partials.rich-text-body', ['label' => $label, 'id' => $id ?? null])
</div>
