@extends('errors.layout')

@section('code', '419')
@section('title', 'Phiên làm việc đã hết hạn')
@section('message', 'Trang đã mở quá lâu. Vui lòng tải lại trang và thử lại.')
@section('link', url()->previous())
@section('action', 'Tải lại trang')
