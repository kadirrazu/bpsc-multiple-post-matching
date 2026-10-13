@extends('layouts.app')
@section('title','Delete Workspace')
@section('content')
<h1 class="mb-3">Delete Workspace</h1>
<div class="card card-body">
<h2 class="h3">{{ $batch->title }}</h2>
<div class="alert alert-danger">Deleting this workspace permanently deletes its posts, imported candidate data, matching groups and review decisions.</div>
<p><strong>{{ $posts }}</strong> posts · <strong>{{ $records }}</strong> imported candidate rows · <strong>{{ $people }}</strong> matching groups</p>
<form method="post" action="{{ route('matching.destroy',$batch) }}">@csrf @method('DELETE')
<label class="form-label" for="confirmation">Type DELETE to confirm</label>
<input class="form-control" id="confirmation" name="confirmation" autocomplete="off" pattern="DELETE" required>
<div class="mt-3 d-flex gap-2"><button class="btn btn-danger">Delete Workspace &amp; Data</button><a class="btn btn-outline-secondary" href="{{ route('matching.index') }}">Cancel</a></div>
</form></div>
@endsection
