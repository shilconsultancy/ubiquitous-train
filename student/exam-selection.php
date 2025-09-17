<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ACCA Mock Exam Portal</title>

    <script src="https://cdn.tailwindcss.com"></script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        /* Applying the Inter font across the page */
        body {
            font-family: 'Inter', sans-serif;
        }
        /* A subtle background pattern for a professional feel */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: radial-gradient(#d1d5db 1px, transparent 1px);
            background-size: 16px 16px;
            opacity: 0.1;
            z-index: -1;
        }

        /* --- NEW SIMPLIFIED MODAL STYLING --- */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }
        .modal-overlay.visible {
            opacity: 1;
            visibility: visible;
        }
        .modal-content {
            background: white;
            padding: 2rem;
            border-radius: 1rem;
            width: 100%;
            max-width: 400px; /* Smaller modal */
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            transform: scale(0.95);
            transition: transform 0.3s ease;
        }
        .modal-overlay.visible .modal-content {
            transform: scale(1);
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 flex items-center justify-center min-h-screen p-4">

    <div class="w-full max-w-lg p-8 space-y-8 bg-white rounded-2xl shadow-2xl shadow-slate-300/60">
        <div class="text-center">
            <h1 class="text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl">ACCA Mock Exam Portal</h1>
            <p class="mt-3 text-slate-600">Prepare for success. Select your exam to begin.</p>
        </div>

        <div id="selectionContainer" class="space-y-6">
            <div>
                <label for="level" class="block text-sm font-medium leading-6 text-slate-900">1. Select Your Level</label>
                <div class="mt-2">
                    <select id="level" name="level" required class="block w-full rounded-md border-0 py-3 px-4 text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-[rgb(197,26,29)] sm:text-sm sm:leading-6 transition-all duration-200">
                        <option value="" disabled selected>Choose a level...</option>
                        <option value="foundation">Foundations in Accountancy</option>
                        <option value="applied_knowledge">Applied Knowledge</option>
                        <option value="applied_skills">Applied Skills</option>
                        <option value="strategic_professional">Strategic Professional</option>
                    </select>
                </div>
            </div>
            <div>
                <label for="subject" class="block text-sm font-medium leading-6 text-slate-900">2. Select Your Subject</label>
                <div class="mt-2">
                    <select id="subject" name="subject" required disabled class="block w-full rounded-md border-0 py-3 px-4 text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-[rgb(197,26,29)] sm:text-sm sm:leading-6 disabled:bg-slate-100 disabled:cursor-not-allowed">
                        <option value="" disabled selected>First, select a level...</option>
                    </select>
                </div>
            </div>
            <div id="examWarning" class="rounded-md bg-yellow-50 p-4 mt-6 hidden">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-yellow-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-yellow-800">Please Note the Exam Rules</h3>
                        <div class="mt-2 text-sm text-yellow-700">
                            <ul class="list-disc space-y-1 pl-5">
                                <li>The exam consists of <strong>50 questions</strong>.</li>
                                <li>You will have <strong>2 hours</strong> to complete it.</li>
                                <li>Refreshing the page or closing the window will <strong>end the exam</strong>.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            <div class="pt-4">
                <button id="startExamBtn" type="button" disabled class="flex w-full justify-center rounded-md bg-[rgb(197,26,29)] px-3 py-3 text-sm font-semibold leading-6 text-white shadow-sm hover:bg-[rgb(177,23,26)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[rgb(197,26,29)] transition-all duration-200 disabled:bg-red-300 disabled:cursor-not-allowed">
                    Start Mock Exam
                </button>
            </div>
        </div>
    </div>

    <div id="confirmationModal" class="modal-overlay">
        <div class="modal-content text-center">
            <h2 class="text-2xl font-bold mb-4">Confirm Your Selection</h2>
            <p class="mb-6 text-slate-600">You are about to start the <strong id="selectedExamText"></strong> exam. Please confirm that you are ready to begin.</p>
            <div class="mt-8 flex justify-center gap-4">
                <button id="cancelBtn" class="px-6 py-2 bg-slate-200 text-slate-800 font-semibold rounded-md hover:bg-slate-300 transition">Cancel</button>
                <button id="finalStartBtn" class="px-6 py-2 bg-[rgb(197,26,29)] text-white font-semibold rounded-md hover:bg-[rgb(177,23,26)] transition">Start Exam</button>
            </div>
        </div>
    </div>

    <script>
        // Data for the subjects based on their level
        const subjects = {
            foundation: [{ id: 'FA1', name: 'Recording Financial Transactions (FA1)' }, { id: 'MA1', name: 'Management Information (MA1)' }, { id: 'FA2', name: 'Maintaining Financial Records (FA2)' }, { id: 'MA2', name: 'Managing Costs and Finance (MA2)' }],
            applied_knowledge: [{ id: 'BT', name: 'Business and Technology (BT)' }, { id: 'MA', name: 'Management Accounting (MA)' }, { id: 'FA', name: 'Financial Accounting (FA)' }],
            applied_skills: [{ id: 'LW', name: 'Corporate and Business Law (LW)' }, { id: 'PM', name: 'Performance Management (PM)' }, { id: 'TX', name: 'Taxation (TX)' }, { id: 'FR', name: 'Financial Reporting (FR)' }, { id: 'AA', name: 'Audit and Assurance (AA)' }, { id: 'FM', name: 'Financial Management (FM)' }],
            strategic_professional: [{ id: 'SBL', name: 'Strategic Business Leader (SBL)' }, { id: 'SBR', name: 'Strategic Business Reporting (SBR)' }, { id: 'AFM', name: 'Advanced Financial Management (AFM)' }, { id: 'APM', name: 'Advanced Performance Management (APM)' }, { id: 'ATX', name: 'Advanced Taxation (ATX)' }, { id: 'AAA', name: 'Advanced Audit and Assurance (AAA)' }]
        };

        // Get the HTML elements for the main page
        const levelSelect = document.getElementById('level');
        const subjectSelect = document.getElementById('subject');
        const startButton = document.getElementById('startExamBtn');
        const examWarning = document.getElementById('examWarning');
        
        // Modal elements
        const modal = document.getElementById('confirmationModal');
        const selectedExamText = document.getElementById('selectedExamText');
        const cancelBtn = document.getElementById('cancelBtn');
        const finalStartBtn = document.getElementById('finalStartBtn');

        // Listen for changes on the level dropdown
        levelSelect.addEventListener('change', () => {
            const selectedLevel = levelSelect.value;
            subjectSelect.innerHTML = '<option value="" disabled selected>First, select a level...</option>';
            examWarning.classList.add('hidden');
            if (selectedLevel && subjects[selectedLevel]) {
                subjects[selectedLevel].forEach(subject => {
                    const option = document.createElement('option');
                    option.value = subject.id;
                    option.textContent = subject.name;
                    subjectSelect.appendChild(option);
                });
                subjectSelect.disabled = false;
            } else {
                subjectSelect.disabled = true;
            }
            validateSelection();
        });

        // Listen for changes on the subject dropdown
        subjectSelect.addEventListener('change', () => {
            if (subjectSelect.value) {
                examWarning.classList.remove('hidden');
            } else {
                examWarning.classList.add('hidden');
            }
            validateSelection();
        });

        // Function to enable/disable the start button
        function validateSelection() {
            const isLevelSelected = levelSelect.value !== '';
            const isSubjectSelected = subjectSelect.value !== '';
            startButton.disabled = !(isLevelSelected && isSubjectSelected);
        }
        
        // Show the modal when the main start button is clicked
        startButton.addEventListener('click', () => {
            const selectedSubjectName = subjectSelect.options[subjectSelect.selectedIndex].text;
            selectedExamText.textContent = selectedSubjectName;
            modal.classList.add('visible');
        });

        // Hide the modal when the cancel button is clicked
        cancelBtn.addEventListener('click', () => {
            modal.classList.remove('visible');
        });
        
        // Hide modal if overlay is clicked
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.classList.remove('visible');
            }
        });

        // Final Start Button - The redirection logic
        finalStartBtn.addEventListener('click', () => {
            const selectedSubject = subjectSelect.value;
            if (selectedSubject) {
                const examPageUrl = `exam_${selectedSubject.toLowerCase()}.php`;
                window.location.href = examPageUrl;
            }
        });

    </script>
</body>
</html>