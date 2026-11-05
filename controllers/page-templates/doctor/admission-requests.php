<?php
if (!defined('APP_RENDERER')) { http_response_code(404); exit; }

require_once __DIR__ . '/../../../controllers/DoctorPortalController.php';
require_once __DIR__ . '/../../../controllers/DoctorHelper.php';

$doctorId = DoctorHelper::getCurrentDoctorId($pdo);
$controller = new DoctorPortalController($pdo, $doctorId);
$patients = $controller->getEmergencyPatientsForAdmission();
?>

<div class="mb-8">
    <h1 class="text-2xl font-bold text-slate-900 tracking-tight">For Admission</h1>
    <p class="mt-1 text-sm text-slate-500">Request inpatient conversion for an assigned emergency patient. The Emergency status remains unchanged until nursing completes the admission.</p>
</div>

<div id="admissionRequestAlert" class="hidden mb-5 rounded-lg border px-4 py-3 text-sm" role="status"></div>

<div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-slate-600">
                <tr>
                    <th class="px-6 py-3 text-left font-medium">Patient</th>
                    <th class="px-6 py-3 text-left font-medium">Emergency Bed</th>
                    <th class="px-6 py-3 text-left font-medium">Arrival</th>
                    <th class="px-6 py-3 text-left font-medium">Status</th>
                    <th class="px-6 py-3 text-right font-medium">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (!$patients): ?>
                    <tr><td colspan="5" class="px-6 py-10 text-center text-slate-400">No active emergency patients are assigned to you.</td></tr>
                <?php else: ?>
                    <?php foreach ($patients as $patient): ?>
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-4">
                                <p class="font-semibold text-slate-900"><?= htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']) ?></p>
                                <p class="text-xs text-slate-500">#<?= (int)$patient['admission_id'] ?></p>
                                <p class="mt-1 max-w-md truncate text-xs text-slate-500"><?= htmlspecialchars($patient['chief_complaint'] ?? 'No chief complaint recorded') ?></p>
                            </td>
                            <td class="px-6 py-4 text-slate-600">
                                <?= htmlspecialchars(trim(($patient['room_number'] ?? 'No room') . (!empty($patient['room_type_name']) ? ' · ' . $patient['room_type_name'] : ''))) ?>
                            </td>
                            <td class="px-6 py-4 text-slate-600"><?= htmlspecialchars(date('M j, Y g:i A', strtotime($patient['admission_datetime']))) ?></td>
                            <td class="px-6 py-4">
                                <?php if (!empty($patient['inpatient_requested_at'])): ?>
                                    <span class="inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">For Admission</span>
                                    <p class="mt-1 text-xs text-slate-500">Nursing has been notified</p>
                                <?php else: ?>
                                    <span class="inline-flex rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-700">Emergency</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <?php if (!empty($patient['inpatient_requested_at'])): ?>
                                    <span class="text-xs font-medium text-emerald-700">Request sent</span>
                                <?php else: ?>
                                    <button type="button"
                                            data-admission-id="<?= (int)$patient['admission_id'] ?>"
                                            data-patient-name="<?= htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name'], ENT_QUOTES, 'UTF-8') ?>"
                                            class="mark-for-admission inline-flex items-center rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60">
                                        Mark For Admission
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
(() => {
    document.querySelectorAll('.mark-for-admission').forEach(button => {
        button.addEventListener('click', async () => {
            const name = button.dataset.patientName;
            const confirmation = await window.Swal.fire({
                icon: 'question',
                title: 'Mark patient for admission?',
                text: `Nursing staff will complete the inpatient conversion for ${name}.`,
                showCancelButton: true,
                confirmButtonText: 'Yes, mark for admission',
                cancelButtonText: 'Cancel',
                reverseButtons: true,
            });
            if (!confirmation.isConfirmed) return;

            const row = button.closest('tr');
            const statusCell = row?.children[3];
            const actionCell = button.closest('td');
            if (!row || !statusCell || !actionCell) {
                await window.Swal.fire({
                    icon: 'error',
                    title: 'Could not mark patient',
                    text: 'The patient row is no longer available. Refresh the page and try again.',
                });
                return;
            }
            button.disabled = true;
            try {
                const response = await fetch(`${document.body.dataset.baseUrl}/api/doctors.php?operation=mark-for-inpatient-admission`, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ admission_id: Number(button.dataset.admissionId) }),
                });
                const result = await response.json();
                if (!response.ok || !result.success) {
                    throw new Error(result.message || 'Could not mark the patient for admission.');
                }
                actionCell.innerHTML = '<span class="text-xs font-medium text-emerald-700">Request sent</span>';
                statusCell.innerHTML = '<span class="inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">For Admission</span><p class="mt-1 text-xs text-slate-500">Nursing has been notified</p>';
                await window.Swal.fire({
                    icon: 'success',
                    title: 'Patient marked for admission',
                    text: result.message,
                });
            } catch (error) {
                button.disabled = false;
                await window.Swal.fire({
                    icon: 'error',
                    title: 'Could not mark patient',
                    text: error.message || 'Please try again.',
                });
            }
        });
    });
})();
</script>
