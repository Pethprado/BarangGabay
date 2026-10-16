<?php
/**
 * Staff: Print Queue — approved documents residents asked to have printed.
 * The approved PDF is printed as is; nothing is retyped.
 *
 * Variables: $queue
 */
use App\Models\DocumentRequest;

ob_start();
?>

<div class="mb-4">
    <p class="mb-0" style="font-size:.68rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--text-muted);">Documents</p>
    <h1 class="mb-0 mt-1" style="font-size:1.55rem;font-weight:800;color:var(--text-primary);line-height:1.1;">Print Queue</h1>
    <p class="text-muted mt-1 mb-0" style="font-size:.82rem;max-width:760px;">
        Residents who asked for a printed copy of an approved document. Open the PDF, print it as it is, then mark it printed;
        the resident is notified to claim it at the hall.
    </p>
</div>

<div class="admin-card">
    <?php if ($queue === []): ?>
        <p class="text-muted mb-0"><i class="bi bi-check2-circle me-1" aria-hidden="true"></i>Nothing to print right now.</p>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table align-middle mb-0" style="font-size:.86rem;">
            <thead><tr><th>Requested</th><th>Resident</th><th>Document</th><th>Control no.</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($queue as $row): ?>
                <tr>
                    <td class="text-nowrap"><?= e(date('M d, Y g:i A', strtotime((string) $row['print_requested_at']))) ?></td>
                    <td><strong><?= e((string) $row['full_name']) ?></strong><div class="text-muted" style="font-size:.75rem;"><?= e((string) ($row['zone'] ?? '')) ?><?= !empty($row['phone']) ? ' · ' . e((string) $row['phone']) : '' ?></div></td>
                    <td><?= e(DocumentRequest::label((string) $row['document_type'])) ?></td>
                    <td class="font-monospace"><?= e((string) $row['certificate_no']) ?></td>
                    <td class="text-end text-nowrap">
                        <a href="<?= e(route('admin/documents/' . (int) $row['id'] . '/preview')) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary"><i class="bi bi-printer me-1" aria-hidden="true"></i>Open PDF</a>
                        <form method="post" action="<?= e(route('admin/documents/' . (int) $row['id'] . '/printed')) ?>" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <button class="btn btn-sm btn-success" type="submit"><i class="bi bi-check2 me-1" aria-hidden="true"></i>Mark printed</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php
$content   = ob_get_clean();
$pageTitle ??= 'Print Queue';
require __DIR__ . '/../../layouts/admin.php';
?>
