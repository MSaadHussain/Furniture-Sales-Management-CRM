@extends('layouts.app')
@section('title', 'Add New Order')
@section('breadcrumb')
    <a href="{{ route('orders.index') }}" class="hover:text-brand">Sales</a> / Add New Order
@endsection

@section('content')
    @include('orders._form')
@endsection
