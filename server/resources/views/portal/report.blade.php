@extends('portal.layout')
@section('title', 'Laporan '.$label)
@section('body')
<div style="padding:24px 12px">@include('vendor-console.monthly-report')</div>
@endsection
