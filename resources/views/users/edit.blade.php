@extends('layouts.app')
@section('title', 'Edit ' . $user->name)
@section('breadcrumb')
    <a href="{{ route('users.index') }}" class="hover:text-brand">Users</a> / {{ $user->name }} / Edit
@endsection

@section('content')
    @include('users._form')
@endsection
