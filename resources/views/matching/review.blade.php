@extends('layouts.app')
@inject('comparison', 'App\Services\Matching\FieldComparison')
@section('title', 'Matching review')
@section('content')
<a class="btn btn-outline-secondary mb-3" href="{{ route('matching.show', $batch) }}">Back</a>
<h1>Matching review — {{ $batch->title }}</h1>
<p class="text-secondary">Compare each field before deciding. USER/REG are not matching keys. Missing optional values are not compared.</p>
<div class="d-flex flex-wrap gap-2 mb-3"><span class="badge bg-green-lt">Exact match</span><span class="badge bg-azure-lt">Partial match</span><span class="badge bg-red-lt">Not matched</span><span class="badge bg-secondary-lt">Not compared</span></div>
@forelse($records as $record)
<div class="card mb-4">
 <div class="card-header"><h2 class="card-title">Post {{ $record->post->code }} · Row {{ $record->source_row }} · {{ $record->data['name'] }}</h2></div>
 <div class="card-body"><form method="post" action="{{ route('matching.decide', [$batch, $record]) }}">
  @csrf<input type="hidden" name="confirm" value="1">
  <div class="table-responsive mb-3"><table class="table table-bordered table-vcenter">
   <thead><tr><th>Matching field</th><th>Uploaded row</th>
    @foreach($record->suggestions ?? [] as $person => $suggestion)
     @php
$other = $suggested->get($suggestion['record_id']);
@endphp
     <th>@if($other)Post {{ $other->post->code }} · Group {{ $person }}<div class="small text-secondary mt-1">{{ $suggestion['reason'] }}</div>@else Unavailable @endif</th>
    @endforeach
    <th>Remarks</th>
   </tr></thead>
   <tbody>@foreach(['user', 'reg', 'name', 'fname', 'mname', 'b_date', 's_roll', 's_year', 'h_roll', 'h_year', 'nid_no'] as $field)
    @php
$left = $record->identity[$field] ?? $record->data[$field] ?? '';
@endphp
    <tr><th>{{ $field }}</th><td>{{ $left }}</td>
     @foreach($record->suggestions ?? [] as $suggestion)
      @php
$other = $suggested->get($suggestion['record_id']);
@endphp
      <td>{{ $other?->identity[$field] ?? $other?->data[$field] ?? '' }}</td>
     @endforeach
     <td>@forelse($record->suggestions ?? [] as $person => $suggestion)
      @php
       $other = $suggested->get($suggestion['record_id']);
       $right = $other?->identity[$field] ?? $other?->data[$field] ?? '';
       $remark = $comparison->compare($field, $left, $right);
      @endphp
      <div class="mb-1 text-nowrap">@if(count($record->suggestions ?? []) > 1)<span class="small text-secondary">Post {{ $other?->post?->code ?? 'Unavailable' }} · Group {{ $person }}:</span>@endif <span class="badge {{ $remark['color'] }}">{{ $remark['label'] }}</span></div>
     @empty<span class="badge bg-secondary-lt">Not compared</span>@endforelse</td>
    </tr>
   @endforeach</tbody>
  </table></div>
  <fieldset class="mb-3"><legend class="h4 mb-2">Decision</legend><div class="row g-3">
   <div class="col-md-6"><div class="bg-green-lt border rounded p-3 h-100"><h3 class="h4 text-success">Confirm Match</h3>
    @foreach($record->suggestions ?? [] as $person => $suggestion)
     @php
      $other = $suggested->get($suggestion['record_id']);
     @endphp
     @if($other)<label class="form-check mb-2"><input class="form-check-input" type="radio" name="decision" value="link:{{ $person }}" required><span class="form-check-label">Confirm Match — Post {{ $other->post->code }} · Group {{ $person }}</span></label>@endif
    @endforeach
   </div></div>
   <div class="col-md-6"><div class="bg-red-lt border rounded p-3 h-100"><h3 class="h4 text-danger">Reject Match</h3><label class="form-check mb-2"><input class="form-check-input" type="radio" name="decision" value="new" required><span class="form-check-label">Reject Match — keep as a separate person</span></label></div></div>
  </div></fieldset>
  <button class="btn btn-primary">Save Decision</button>
 </form></div>
</div>
@empty<div class="alert alert-success">No pending matching reviews.</div>@endforelse
{{ $records->links() }}
@endsection
