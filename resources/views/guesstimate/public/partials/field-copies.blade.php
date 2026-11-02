{{-- Kopie pól (Duplikuj): samodzielne teksty strony. $fields, $place. --}}
@php $copies = \App\Guesstimate\Document\FieldLayout::copies($fields); @endphp
@if ($copies)
  <div class="osf-copies">
    @foreach ($copies as $copy)
      <p @class(['whitespace-pre-line', 'osf-t-base' => $copy['style']['role'] === 'text', 'osf-t-3xl font-bold tracking-tight' => $copy['style']['role'] === 'heading']) data-field-copy {{ $place($copy['id']) }}>{{ $copy['text'] }}</p>
    @endforeach
  </div>
@endif
