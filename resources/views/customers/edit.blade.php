@extends('layouts.app')
@section('title', 'Edit ' . $customer->name)
@section('breadcrumb')
    <a href="{{ route('customers.index') }}" class="hover:text-brand">Customers</a> /
    <a href="{{ route('customers.show', $customer) }}" class="hover:text-brand">{{ $customer->name }}</a> / Edit
@endsection

@section('content')
    @include('customers._form')
@endsection
