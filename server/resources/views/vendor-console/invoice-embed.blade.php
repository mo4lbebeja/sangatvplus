<div style="background:#E2E8F0;border-radius:16px;padding:24px 12px" class="dark:bg-gray-800">
    @include('vendor-console.invoice-document', ['invoice' => $getRecord()->loadMissing('hotel')])
</div>
