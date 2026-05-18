<!DOCTYPE html>
<html>
<head>
    <title>Poultry Inventory System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >
</head>

<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="/inventory">
            Poultry Inventory API System
        </a>

        <div>
            <a href="/inventory" class="btn btn-outline-light btn-sm">
                Inventory Dashboard
            </a>

            <a href="/api/egg-inventory" class="btn btn-warning btn-sm" target="_blank">
                View JSON API
            </a>
        </div>
    </div>
</nav>

<div class="container mt-4">

    <h3>Inventory Dashboard</h3>
    <p class="text-muted">
        This page displays egg inventory records received from the Poultry Egg System.
    </p>

    <!-- TOTAL EGGS CARD -->
    <div class="row mt-4 mb-4">
        <div class="col-md-12">
            <div class="card shadow-sm border-0 bg-warning">
                <div class="card-body">
                    <h5>Total Eggs in Inventory</h5>
                    <h1>{{ $totalEggs }}</h1>
                </div>
            </div>
        </div>
    </div>

    <!-- EGG SIZE SUMMARY CARDS -->
    <div class="row mb-4">

        @php
            $sizes = ['Large', 'Medium', 'Small', 'Cracked'];
        @endphp

        @foreach ($sizes as $size)
            @php
                $total = 0;

                foreach ($summary as $item) {
                    if ($item->egg_size === $size) {
                        $total = $item->total_quantity;
                    }
                }
            @endphp

            <div class="col-md-3">
                <div class="card shadow-sm border-0">
                    <div class="card-body">
                        <h6>{{ $size }} Eggs</h6>
                        <h2>{{ $total }}</h2>
                    </div>
                </div>
            </div>
        @endforeach

    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-dark text-white">
            Egg Inventory Records
        </div>

        <div class="card-body">

            <table class="table table-bordered table-striped">
                <thead class="table-dark">
                    <tr>
                        <th>Batch Code</th>
                        <th>Egg Size</th>
                        <th>Quantity</th>
                        <th>Received Date</th>
                        <th>Date Created</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($inventories as $inventory)
                        <tr>
                            <td>{{ $inventory->batch_code }}</td>
                            <td>{{ $inventory->egg_size }}</td>
                            <td>{{ $inventory->quantity }}</td>
                            <td>{{ $inventory->received_date }}</td>
                            <td>{{ $inventory->created_at }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center">
                                No inventory records found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

        </div>
    </div>

</div>

</body>
</html>
