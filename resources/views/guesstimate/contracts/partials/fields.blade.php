{{-- Tokeny użyte w treści. Puste pole oznacza wartość z danych; `name` w nawiasach zachowuje kropki w kluczach. Kwoty wynikają z harmonogramu. --}}
@foreach ($fields as $field)
  @php($id = 'f-value-' . str_replace('.', '-', $field['key']))
  <x-gst.field :label="$field['label']" :for="$id" :hint="$field['derived'] ? 'Z harmonogramu płatności.' : ($field['manual'] ? 'Wpisywane ręcznie.' : ($field['source'] !== null ? 'Z danych: ' . $field['source'] : 'Brak w danych źródłowych.'))">
    <x-gst.input :name="'values[' . $field['key'] . ']'" :id="$id" :value="$posted[$field['key']] ?? ($field['overridden'] ? $field['value'] : ($field['manual'] ? '' : $field['source']))" :disabled="$readonly || $field['derived']" />
  </x-gst.field>
@endforeach
