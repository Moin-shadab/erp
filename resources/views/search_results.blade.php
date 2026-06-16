<div class="container-fluid p-0">
    <!-- Search Header -->
    <div class="row mb-4">
        <div class="col-12">
            <h4 class="fw-bold mb-1"><i class="bi bi-search me-2 text-primary"></i>Global Search Results</h4>
            <p class="text-muted small">Showing results for query: <strong class="text-dark">"{{ $q }}"</strong></p>
        </div>
    </div>

    <!-- Customers Matches -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3 border-0">
            <h6 class="fw-bold mb-0 text-primary"><i class="bi bi-people me-2"></i>Customers Matches ({{ count($customers) }})</h6>
        </div>
        <div class="list-group list-group-flush" style="font-size:0.85rem;">
            @forelse($customers as $c)
            <a href="#" class="list-group-item list-group-item-action py-3 d-flex justify-content-between align-items-center" onclick="navigateToSlug('customers')">
                <div>
                    <strong class="d-block">{{ $c->name }}</strong>
                    <span class="text-muted small">{{ $c->email }} | {{ $c->phone }}</span>
                </div>
                <span class="badge bg-light text-muted border">Customer #{{ $c->id }}</span>
            </a>
            @empty
            <div class="p-3 text-muted">No matching customers found.</div>
            @endforelse
        </div>
    </div>

    <!-- Invoices Matches -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3 border-0">
            <h6 class="fw-bold mb-0 text-primary"><i class="bi bi-receipt me-2"></i>Sales Invoices Matches ({{ count($invoices) }})</h6>
        </div>
        <div class="list-group list-group-flush" style="font-size:0.85rem;">
            @forelse($invoices as $i)
            <a href="#" class="list-group-item list-group-item-action py-3 d-flex justify-content-between align-items-center" onclick="navigateToSlug('sales-invoices')">
                <div>
                    <strong class="d-block">{{ $i->invoice_no }}</strong>
                    <span class="text-muted small">Invoice Date: {{ $i->invoice_date }} | Amount: ₹{{ number_format($i->total_amount, 2) }}</span>
                </div>
                <span class="badge bg-{{ $i->status === 'Paid' ? 'success' : ($i->status === 'Pending Approval' ? 'warning text-dark' : 'secondary') }}">{{ $i->status }}</span>
            </a>
            @empty
            <div class="p-3 text-muted">No matching invoices found.</div>
            @endforelse
        </div>
    </div>

    <!-- Inventory Matches -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3 border-0">
            <h6 class="fw-bold mb-0 text-primary"><i class="bi bi-box me-2"></i>Inventory Items Matches ({{ count($inventory) }})</h6>
        </div>
        <div class="list-group list-group-flush" style="font-size:0.85rem;">
            @forelse($inventory as $item)
            <a href="#" class="list-group-item list-group-item-action py-3 d-flex justify-content-between align-items-center" onclick="navigateToSlug('inventory-items')">
                <div>
                    <strong class="d-block">{{ $item->name }}</strong>
                    <span class="text-muted small">SKU: {{ $item->item_code }} | Qty: {{ $item->qty_on_hand }} units | Price: ${{ number_format($item->unit_price, 2) }}</span>
                </div>
                <span class="badge bg-{{ $item->status === 'In Stock' ? 'success' : ($item->status === 'Low Stock' ? 'warning text-dark' : 'danger') }}">{{ $item->status }}</span>
            </a>
            @empty
            <div class="p-3 text-muted">No matching inventory items found.</div>
            @endforelse
        </div>
    </div>
</div>

