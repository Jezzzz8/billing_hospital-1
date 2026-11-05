<?php
if (!defined('APP_RENDERER')) { http_response_code(404); exit; }

require_once __DIR__ . '/../../../controllers/NurseController.php';

$controller = new NurseController($pdo);
$careScope = ($_GET['page'] ?? '') === 'nurse-emergency-create' ? 'ER' : 'INPATIENT';
$genders = $controller->getGenders();
$roomTypes = $controller->getRoomTypes($careScope);
$emergencyRoomType = $careScope === 'ER' ? ($roomTypes[0] ?? null) : null;
$availableRooms = $controller->getAvailableRooms(null, $careScope);
$doctors = $controller->getDoctors($careScope);
$availableBedsByType = [];
foreach ($availableRooms as $room) {
    $typeId = (int)$room['room_type_id'];
    $availableBedsByType[$typeId] = ($availableBedsByType[$typeId] ?? 0) + (int)$room['available_beds'];
}

$selectedPatientId = (int)($_GET['patient_id'] ?? 0);
$selectedPatient = $selectedPatientId
    ? $controller->getPatientDetails($selectedPatientId)
    : null;
?>

<?php if ($selectedPatient && !empty($selectedPatient['current_admission'])
    && ($careScope === 'ER' || ($selectedPatient['current_admission']['care_scope'] ?? '') !== 'ER')): ?>
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Patient Already Has an Active Encounter</h1>
        <p class="mt-1 text-sm text-slate-500">Manage the existing encounter instead of creating another one.</p>
    </div>
    <div class="rounded-xl border border-amber-200 bg-amber-50 p-6">
        <p class="font-semibold text-amber-900">
            <?= htmlspecialchars($selectedPatient['first_name'] . ' ' . $selectedPatient['last_name']) ?>
            already has active <?= ($selectedPatient['current_admission']['care_scope'] ?? '') === 'ER' ? 'ER visit' : 'admission' ?>
            #<?= (int)$selectedPatient['current_admission']['admission_id'] ?>.
        </p>
        <a href="<?= BASE_URL ?>/pages/nurse/nurse-room-assignments.html?admission_id=<?= (int)$selectedPatient['current_admission']['admission_id'] ?>"
           class="mt-4 inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
            Manage existing encounter
        </a>
    </div>
<?php else: ?>
<div class="mb-8">
    <h1 class="text-2xl font-bold text-slate-900 tracking-tight"><?= $careScope === 'ER' ? 'Emergency Patient' : 'New Admission' ?></h1>
    <p class="mt-1 text-sm text-slate-500">
        <?= $careScope === 'ER'
            ? 'Register an emergency visit, assign an Emergency Bed, and choose the Emergency Doctor(s).'
            : 'Admit a patient, assign a room, and choose the Admission Doctor(s).' ?>
    </p>
</div>

<div id="alert" class="hidden mb-5 rounded-lg px-4 py-3 text-sm border"></div>

<div class="bg-white rounded-xl border border-slate-200 p-6 max-w-3xl">
    <?php if ($careScope === 'ER' && empty($availableRooms)): ?>
        <div class="mb-5 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            No Emergency Bed is currently available. An ER visit cannot be created until an Emergency Bed has capacity.
        </div>
    <?php endif; ?>
    <form id="admissionForm" method="POST" novalidate data-care-scope="<?= htmlspecialchars($careScope) ?>">
        <input type="hidden" name="patient_id" id="patient_id" value="<?= $selectedPatientId ?>">

        
        <div class="mb-6">
            <label class="block text-sm font-medium text-slate-700 mb-2">Patient <span class="text-rose-500">*</span></label>
            
            <?php if ($selectedPatient): ?>
                <div class="flex items-center gap-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                    <div class="w-12 h-12 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center font-semibold shrink-0">
                        <?= strtoupper(substr($selectedPatient['first_name'], 0, 1) . substr($selectedPatient['last_name'], 0, 1)) ?>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-semibold text-slate-900"><?= htmlspecialchars($selectedPatient['first_name'] . ' ' . $selectedPatient['last_name']) ?></p>
                        <p class="text-xs text-slate-500"><?= htmlspecialchars($selectedPatient['gender_name']) ?> · <?= htmlspecialchars($selectedPatient['birth_date'] ?? '—') ?></p>
                    </div>
                    <a href="<?= BASE_URL ?>/pages/nurse/nurse-patient-search.html"
                       class="text-xs font-medium text-blue-600 hover:text-blue-700">Change</a>
                </div>
            <?php else: ?>
                <div class="relative">
                    <input type="text" id="patientSearch"
                           placeholder="<?= $careScope === 'ER' ? 'Search registered patient by name, contact, or email…' : 'Search current Emergency patients by name, contact, or email…' ?>"
                           data-search-scope="<?= $careScope === 'INPATIENT' ? 'INPATIENT' : '' ?>"
                           class="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <div id="patientResults" class="hidden absolute z-10 w-full mt-1 bg-white border border-slate-200 rounded-lg shadow-lg max-h-64 overflow-y-auto"></div>
                </div>
                <p id="patientSearchHelp" class="mt-1.5 text-xs text-slate-500">
                    <?= $careScope === 'ER'
                        ? 'Search registered patient records to start an emergency visit.'
                        : 'Search current Emergency patients or registered patients to readmit.' ?>
                </p>
            <?php endif; ?>
            <p class="mt-1.5 text-xs text-red-600 hidden" data-error-for="patient_id"></p>
        </div>

        
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5 mb-6">
            <?php if ($careScope === 'ER'): ?>
                <input type="hidden" name="admission_type" id="admission_type" value="Urgent">
                <div>
                    <span class="block text-sm font-medium text-slate-700 mb-1.5">Encounter Type</span>
                    <p class="rounded-lg border border-amber-200 bg-amber-50 px-3.5 py-2.5 text-sm font-medium text-amber-900">Emergency visit</p>
                </div>
            <?php else: ?>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Admission Type</label>
                <select name="admission_type" id="admission_type"
                        class="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="Elective">Elective</option>
                    <option value="Urgent" selected>Urgent</option>
                </select>
                <p class="mt-1.5 text-xs text-red-600 hidden" data-error-for="admission_type"></p>
            </div>
            <?php endif; ?>

            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Chief Complaint</label>
                <input type="text" name="chief_complaint" id="chief_complaint"
                       value="<?= htmlspecialchars((string)($selectedPatient['current_admission']['chief_complaint'] ?? '')) ?>"
                       placeholder="e.g. Chest pain and dizziness"
                       class="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>

            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-slate-700 mb-1.5"><?= $careScope === 'ER' ? 'Emergency Notes' : 'Admission Notes' ?></label>
                <textarea name="admission_notes" id="admission_notes" rows="2"
                          class="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"></textarea>
            </div>
        </div>

        
        <div class="mb-6">
            <div class="flex items-center justify-between mb-2">
                <label class="block text-sm font-medium text-slate-700">
                    Attending Doctor(s)
                    <span class="text-slate-400 font-normal ml-1">(select one or more)</span>
                </label>
                <span id="doctorCount" class="text-xs text-slate-500">0 selected</span>
            </div>
            <p class="text-xs text-slate-500 mb-3">
                <?= $careScope === 'ER' ? 'Emergency Doctors' : 'Admission Doctors' ?> assigned here appear on the doctor’s dashboard,
                patient list, service requests, and diagnosis pages.
            </p>

            <?php if (empty($doctors)): ?>
                <p class="text-xs text-slate-400 italic border border-slate-200 rounded-lg p-3">
                    No active doctors available. Add a doctor first from the Doctors page.
                </p>
            <?php else: ?>
                <div class="space-y-2 border border-slate-200 rounded-lg p-3 max-h-64 overflow-y-auto">
                    <?php foreach ($doctors as $d): ?>
                        <label class="doctor-option flex items-center gap-3 p-2 rounded-lg hover:bg-slate-50 cursor-pointer transition"
                               data-care-scope="<?= htmlspecialchars($d['care_scope']) ?>">
                            <input type="checkbox"
                                   name="doctor_ids[]"
                                   value="<?= (int)$d['doctor_id'] ?>"
                                   class="doctor-checkbox rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-slate-900">
                                    Dr. <?= htmlspecialchars($d['first_name'] . ' ' . $d['last_name']) ?>
                                </p>
                                <p class="text-xs text-slate-500">
                                    <?= $careScope === 'ER' ? 'Emergency Doctor' : 'Admission Doctor' ?>
                                    · Consultation fee: ₱<?= number_format((float)$d['consultation_fee'], 2) ?>
                                </p>
                            </div>
                            <select name="doctor_roles[<?= (int)$d['doctor_id'] ?>]"
                                    class="text-xs rounded-lg border border-slate-300 px-2 py-1 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="Attending">Attending</option>
                                <option value="Consulting">Consulting</option>
                                <option value="Referring">Referring</option>
                            </select>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        
        <div class="mb-6">
            <?php if ($careScope === 'ER'): ?>
                <input type="hidden" name="preferred_room_type_id" id="preferred_room_type_id"
                       value="<?= (int)($emergencyRoomType['room_type_id'] ?? 0) ?>">
                <p class="block text-sm font-medium text-slate-700 mb-2">Emergency Bed</p>
                <div class="rounded-lg border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-700">
                    Emergency Bed · <?= (int)($availableBedsByType[(int)($emergencyRoomType['room_type_id'] ?? 0)] ?? 0) ?> available
                </div>
            <?php else: ?>
                <label for="preferred_room_type_id" class="block text-sm font-medium text-slate-700 mb-2">
                    Patient's preferred room type <span class="text-rose-500">*</span>
                </label>
                <select name="preferred_room_type_id" id="preferred_room_type_id" required
                        class="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">— Select preferred room type —</option>
                    <?php foreach ($roomTypes as $rt): ?>
                        <?php $availableBeds = (int)($availableBedsByType[(int)$rt['room_type_id']] ?? 0); ?>
                        <option value="<?= (int)$rt['room_type_id'] ?>"
                                data-available-beds="<?= $availableBeds ?>"
                                <?= $availableBeds === 0 ? 'disabled' : '' ?>>
                            <?= htmlspecialchars($rt['room_type_name']) ?> · <?= $availableBeds ?> bed<?= $availableBeds === 1 ? '' : 's' ?> available · ₱<?= number_format((float)$rt['rate_per_day'], 2) ?>/<?= htmlspecialchars(['once' => 'once', 'hour' => 'hour', 'day' => 'day'][$rt['charge_interval']] ?? 'day') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
            <p class="mt-1.5 text-xs text-red-600 hidden" data-error-for="preferred_room_type_id"></p>
            <div id="roomSelectionFields" <?= $careScope === 'ER' ? '' : 'hidden' ?>>
                <label for="room_id" class="mt-4 block text-sm font-medium text-slate-700 mb-2">
                    <?= $careScope === 'ER' ? 'Specific Emergency Bed' : 'Specific room / ward bed' ?>
                    <span class="text-rose-500">*</span>
                </label>
                <select name="room_id" id="room_id" required
                        class="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 disabled:bg-slate-50 disabled:text-slate-400">
                    <option value="">— <?= $careScope === 'ER' ? 'No Emergency Beds available' : 'Select a room type first' ?> —</option>
                    <?php foreach ($availableRooms as $room): ?>
                        <?php if ((int)$room['available_beds'] <= 0) continue; ?>
                        <?php
                        $location = implode(' · ', array_filter([
                            $room['building_name'] ?? '',
                            $room['floor_level_name'] ?? '',
                        ]));
                        $roomLabel = 'Room ' . $room['room_number']
                            . ' · ' . $room['room_type_name']
                            . ($location !== '' ? ' · ' . $location : '')
                            . ' · ' . (int)$room['available_beds'] . ' bed'
                            . ((int)$room['available_beds'] === 1 ? '' : 's')
                            . ' available · ₱' . number_format((float)$room['rate_per_day'], 2)
                            . '/' . (['once' => 'once', 'hour' => 'hour', 'day' => 'day'][$room['charge_interval']] ?? 'day');
                        ?>
                        <option value="<?= (int)$room['room_id'] ?>"
                                data-room-type-id="<?= (int)$room['room_type_id'] ?>">
                            <?= htmlspecialchars($roomLabel) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="mt-1.5 text-xs text-red-600 hidden" data-error-for="room_id"></p>
                <p id="roomAvailabilityMessage" class="mt-2 text-sm text-slate-600" aria-live="polite">
                    <?= $careScope === 'ER'
                        ? 'Select the specific available Emergency Bed for this visit.'
                        : 'Select a specific available room in the preferred room type.' ?>
                </p>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-200">
            <a href="<?= BASE_URL ?>/pages/nurse/nurse.html"
               class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                Cancel
            </a>
            <button type="submit" id="submitBtn"
                    class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60">
                <span id="submitLabel"><?= $careScope === 'ER' ? 'Create Emergency Patient' : 'Admit Patient' ?></span>
            </button>
        </div>
    </form>
</div>

<script>
(function() {
    const baseUrl = document.body.dataset.baseUrl;
    const patientSearch = document.getElementById('patientSearch');
    const patientResults = document.getElementById('patientResults');
    const patientIdInput = document.getElementById('patient_id');
    let searchTimeout = null;
    const escapeHtml = value => String(value).replace(/[&<>"']/g, char => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    })[char]);


    const doctorCheckboxes = document.querySelectorAll('.doctor-checkbox');
    const doctorCountEl = document.getElementById('doctorCount');
    function updateDoctorCount() {
        const n = document.querySelectorAll('.doctor-checkbox:checked').length;
        if (doctorCountEl) doctorCountEl.textContent = n + ' selected';
    }
    doctorCheckboxes.forEach(cb => cb.addEventListener('change', updateDoctorCount));
    if (patientSearch) {
        patientSearch.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const q = this.value.trim();

            if (q.length === 0) {
                patientResults.classList.add('hidden');
                return;
            }

            searchTimeout = setTimeout(async () => {
                try {
                    const response = await axios.get(`${baseUrl}/api/nurses/get-patient.php`, {
                        params: {
                            q,
                                    care_scope: patientSearch.dataset.searchScope || undefined
                        }
                    });
                    const patients = response.data.patients || [];

                    if (patients.length === 0) {
                        const message = patientSearch.dataset.searchScope
                            ? 'No current Emergency or returning patients found'
                            : 'No registered patients found';
                        const registerLink = patientSearch.dataset.searchScope
                            ? ''
                            : `<a class="block border-t border-slate-100 px-4 py-3 text-sm font-medium text-blue-700 hover:bg-blue-50" href="${baseUrl}/pages/nurse/nurse-patient-register.html?name=${encodeURIComponent(q)}">Register a new patient using “${escapeHtml(q)}”</a>`;
                        patientResults.innerHTML = `<div class="px-4 py-3 text-sm text-slate-500">${message}</div>${registerLink}`;
                    } else {
                        patientResults.innerHTML = patients.map(p => `
                            <button type="button" class="patient-option w-full text-left px-4 py-3 hover:bg-slate-50 border-b border-slate-100 last:border-b-0"
                                            data-id="${p.patient_id}" data-name="${p.first_name} ${p.last_name}"
                                            data-active-scope="${p.care_scope || ''}">
                                        <p class="font-medium text-slate-900">${p.first_name} ${p.last_name}</p>
                                        <p class="text-xs text-slate-500">${p.patient_code || ''} ${p.contact_number || p.email || 'No contact'}</p>
                                        ${p.admission_id ? `<span class="text-xs text-amber-600">Current ${p.care_scope === 'ER' ? 'Emergency visit' : 'inpatient admission'}</span>` : '<span class="text-xs text-emerald-700">Available to register</span>'}
                                    </button>
                                `).join('');
                    }
                    patientResults.classList.remove('hidden');

                    patientResults.querySelectorAll('.patient-option').forEach(btn => {
                        btn.addEventListener('click', function() {
                            if (this.dataset.activeScope === 'INPATIENT') {
                                patientResults.innerHTML = '<div class="px-4 py-3 text-sm text-amber-800">This patient already has an active inpatient admission. Manage that admission from Active Patient.</div>';
                                return;
                            }
                            const id = this.dataset.id;
                            const name = this.dataset.name;
                            patientIdInput.value = id;
                            patientSearch.value = name;
                            patientResults.classList.add('hidden');


                            const wrapper = patientSearch.parentElement;
                            wrapper.innerHTML = `
                                <div class="flex items-center gap-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                                    <div class="w-12 h-12 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center font-semibold shrink-0">
                                        ${name.split(' ').map(n => n[0]).join('').toUpperCase()}
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="font-semibold text-slate-900">${name}</p>
                                    </div>
                                    <button type="button" onclick="location.reload()" class="text-xs font-medium text-blue-600 hover:text-blue-700">Change</button>
                                </div>
                            `;
                        });
                    });
                } catch (err) {
                    patientResults.innerHTML = `<div class="px-4 py-3 text-sm text-rose-600">${err.response?.data?.message || 'Could not search patients.'}</div>`;
                    patientResults.classList.remove('hidden');
                }
            }, 300);
        });

        document.addEventListener('click', function(e) {
            if (!patientSearch.contains(e.target) && !patientResults.contains(e.target)) {
                patientResults.classList.add('hidden');
            }
        });
    }


    const preferredRoomType = document.getElementById('preferred_room_type_id');
    const roomSelect = document.getElementById('room_id');
    const roomSelectionFields = document.getElementById('roomSelectionFields');
    const roomAvailabilityMessage = document.getElementById('roomAvailabilityMessage');
    const careScope = document.getElementById('admissionForm').dataset.careScope;
    const roomPlaceholder = roomSelect.options[0];
    const roomOptions = Array.from(roomSelect.options).slice(1);
    function updateAvailableRoomOptions() {
        const selectedTypeId = preferredRoomType.value;
        roomSelectionFields.hidden = selectedTypeId === '';
        const matchingRooms = roomOptions.filter(option =>
            selectedTypeId !== '' && option.dataset.roomTypeId === selectedTypeId
        );
        roomPlaceholder.textContent = matchingRooms.length > 0
            ? '— Select a specific available room —'
            : (selectedTypeId
                ? (careScope === 'ER'
                    ? '— No Emergency Beds available —'
                    : '— No available beds for this room type —')
                : '— Select a room type first —');
        roomSelect.replaceChildren(roomPlaceholder, ...matchingRooms);
        roomSelect.disabled = selectedTypeId === '' || matchingRooms.length === 0;
        roomSelect.value = '';
        return matchingRooms.length;
    }

    preferredRoomType.addEventListener('change', function() {
        const selected = this.options?.[this.selectedIndex];
        const preferredBeds = Number(selected?.dataset.availableBeds || 0);
        const roomsAvailable = updateAvailableRoomOptions();

        if (careScope === 'ER') {
            roomAvailabilityMessage.textContent = roomsAvailable > 0
                ? 'Select the specific available Emergency Bed for this visit.'
                : 'No Emergency Beds are currently available.';
        } else if (!this.value) {
            roomAvailabilityMessage.textContent = 'Choose a room type, then select the specific available room. Admission will not assign a different room automatically.';
        } else if (preferredBeds > 0 && roomsAvailable > 0) {
            roomAvailabilityMessage.textContent = `${roomsAvailable} room(s) have available beds in this room type. Select the exact room above.`;
        } else {
            roomAvailabilityMessage.textContent = 'No beds are currently available in this room type. Choose another type with availability.';
        }
    });

    preferredRoomType.dispatchEvent(new Event('change', { bubbles: true }));


    document.getElementById('admissionForm').addEventListener('submit', async function(e) {
        e.preventDefault();

        const btn = document.getElementById('submitBtn');
        const label = document.getElementById('submitLabel');
        const alertEl = document.getElementById('alert');

        document.querySelectorAll('[data-error-for]').forEach(el => {
            el.textContent = '';
            el.classList.add('hidden');
        });


        const formEl = this;
        const data = {};

        new FormData(formEl).forEach((value, key) => {
            if (key === 'doctor_ids[]') {
                (data.doctor_ids ||= []).push(value);
                return;
            }
            const m = key.match(/^doctor_roles\[(\d+)\]$/);
            if (m) {
                (data.doctor_roles ||= {})[m[1]] = value;
                return;
            }
            data[key] = value;
        });

        const clientErrors = {};

        if (!data.patient_id || data.patient_id === '0') {
            clientErrors.patient_id = 'Please select a patient.';
        }

        if (!data.preferred_room_type_id || data.preferred_room_type_id === '') {
            clientErrors.preferred_room_type_id = 'Please select the patient\'s preferred room type.';
        }
        if (!data.room_id || data.room_id === '') {
            clientErrors.room_id = 'Please select a specific available room.';
        }

        if (Object.keys(clientErrors).length > 0) {
            Object.entries(clientErrors).forEach(([field, msg]) => {
                const el = document.querySelector(`[data-error-for="${field}"]`);
                if (el) { el.textContent = msg; el.classList.remove('hidden'); }
            });

            alertEl.className = 'mb-5 rounded-lg px-4 py-3 text-sm border border-rose-200 bg-rose-50 text-rose-800';
            alertEl.textContent = 'Please fix the highlighted fields.';
            alertEl.classList.remove('hidden');

            const firstError = document.querySelector('[data-error-for]:not(.hidden)');
            if (firstError) {
                firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            return;
        }

        btn.disabled = true;
        label.textContent = <?= json_encode($careScope === 'ER' ? 'Creating ER visit…' : 'Admitting…') ?>;
        alertEl.classList.add('hidden');

        try {
            const endpoint = <?= json_encode($careScope === 'ER'
                ? BASE_URL . '/api/nurse.php?operation=create-er-admission'
                : BASE_URL . '/api/nurses/admission-create.php') ?>;
            const response = await axios.post(endpoint, data, {
                headers: { 'Content-Type': 'application/json' }
            });

            if (response.data.success) {
                alertEl.className = 'mb-5 rounded-lg px-4 py-3 text-sm border border-emerald-200 bg-emerald-50 text-emerald-800';
                alertEl.textContent = `${response.data.message} Patient portal code: ${response.data.patient_code}`;
                alertEl.classList.remove('hidden');

                const continueLink = document.createElement('a');
                continueLink.href = `${baseUrl}/pages/nurse/nurse-room-assignments.html?admission_id=${response.data.admission_id}`;
                continueLink.textContent = 'Continue to Room Assignments';
                continueLink.className = 'ml-2 font-semibold underline';
                alertEl.append(continueLink);
            }
        } catch (err) {
            const res = err.response?.data;
            if (res?.errors) {
                Object.entries(res.errors).forEach(([field, msg]) => {
                    const el = document.querySelector(`[data-error-for="${field}"]`);
                    if (el) { el.textContent = msg; el.classList.remove('hidden'); }
                });
            }
            alertEl.className = 'mb-5 rounded-lg px-4 py-3 text-sm border border-rose-200 bg-rose-50 text-rose-800';
            alertEl.textContent = res?.message || 'Something went wrong.';
            alertEl.classList.remove('hidden');
        } finally {
            btn.disabled = false;
            label.textContent = <?= json_encode($careScope === 'ER' ? 'Create Emergency Patient' : 'Admit Patient') ?>;
        }
    });
})();
</script>
<?php endif; ?>