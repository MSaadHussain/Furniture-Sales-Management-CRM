@extends('layouts.app')
@section('title', 'Edit Order ' . $order->display_number)
@section('breadcrumb')
    <a href="{{ route('orders.index') }}" class="hover:text-brand">Sales</a> /
    <a href="{{ route('orders.show', $order) }}" class="hover:text-brand">{{ $order->display_number }}</a> / Edit
@endsection

@section('content')
    @include('orders._form')
@endsection
