@extends('layouts.app')
@section('title','Edit Workspace')
@section('content')
<h1 class="mb-3">Edit Workspace</h1>
<form class="card card-body" method="post" action="{{ route('matching.update',$batch) }}">
@csrf @method('PUT')
<label class="form-label required" for="title">Workspace title</label>
<input class="form-control" id="title" name="title" maxlength="255" value="{{ old('title',$batch->title) }}" required>
<div class="mt-3 d-flex gap-2"><button class="btn btn-primary">Save Changes</button><a class="btn btn-outline-secondary" href="{{ route('matching.index') }}">Cancel</a></div>
</form>
@endsection
