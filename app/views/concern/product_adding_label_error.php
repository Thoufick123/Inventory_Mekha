<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Product Adding Label Error') ?></title>
    <link rel="stylesheet" href="<?= \App\Core\Controller::url('/assets/css/bootstrap.min.css') ?>">
</head>
<body class="bg-light p-3">
    <div id="productAddingLabelError" class="alert alert-danger mb-0" role="alert">
        <strong>Could not generate labels:</strong> <?= htmlspecialchars($labelError ?? 'An unexpected label error occurred.') ?>
    </div>
    <script>
        if (window.parent !== window) {
            window.parent.postMessage({
                type: 'product-adding-label-error',
                message: <?= json_encode($labelError ?? 'Could not generate labels.', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>
            }, window.location.origin);
        }
    </script>
</body>
</html>
