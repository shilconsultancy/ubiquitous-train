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

        /* --- NEW MODAL STYLING --- */
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
            max-width: 500px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            transform: scale(0.95);
            transition: transform 0.3s ease;
        }
        .modal-overlay.visible .modal-content {
            transform: scale(1);
        }
        .modal-step {
            display: none;
        }
        .modal-step.active {
            display: block;
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

    <div id="studentModal" class="modal-overlay">
        <div class="modal-content">
            <div id="step1" class="modal-step active">
                <h2 class="text-2xl font-bold mb-4">Student Details</h2>
                <p class="mb-6 text-slate-600">Please enter your information to proceed.</p>
                <div class="space-y-4">
                    <div>
                        <label for="studentName" class="block text-sm font-medium text-slate-700">Full Name</label>
                        <input type="text" id="studentName" class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm focus:outline-none focus:ring-[rgb(197,26,29)] focus:border-[rgb(197,26,29)] sm:text-sm">
                        <p id="nameError" class="text-red-500 text-xs mt-1 hidden">Name is required.</p>
                    </div>
                    <div>
                        <label for="studentEmail" class="block text-sm font-medium text-slate-700">Email Address</label>
                        <input type="email" id="studentEmail" class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm focus:outline-none focus:ring-[rgb(197,26,29)] focus:border-[rgb(197,26,29)] sm:text-sm">
                         <p id="emailError" class="text-red-500 text-xs mt-1 hidden">A valid email is required.</p>
                    </div>
                    <div>
                        <label for="accaId" class="block text-sm font-medium text-slate-700">ACCA ID</label>
                        <input type="text" id="accaId" class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm focus:outline-none focus:ring-[rgb(197,26,29)] focus:border-[rgb(197,26,29)] sm:text-sm">
                        <p id="idError" class="text-red-500 text-xs mt-1 hidden">ACCA ID is required.</p>
                    </div>
                </div>
                <div class="mt-8 flex justify-end">
                    <button id="nextStep1" class="px-6 py-2 bg-[rgb(197,26,29)] text-white font-semibold rounded-md hover:bg-[rgb(177,23,26)] transition">Next</button>
                </div>
            </div>

            <div id="step2" class="modal-step">
                <h2 class="text-2xl font-bold mb-4">Terms & Conditions</h2>
                <p class="mb-6 text-slate-600">Please review and accept the terms to continue.</p>
                <div class="space-y-4">
                    <div class="flex items-start">
                        <input id="psbTerms" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-[rgb(197,26,29)] focus:ring-[rgb(177,23,26)]">
                        <label for="psbTerms" class="ml-2 block text-sm text-gray-900">I accept the <a href="#" class="font-medium text-[rgb(197,26,29)] hover:underline">PSB T&C</a>.</label>
                    </div>
                    <div class="flex items-start">
                        <input id="accaTerms" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-[rgb(197,26,29)] focus:ring-[rgb(177,23,26)]">
                        <label for="accaTerms" class="ml-2 block text-sm text-gray-900">I accept the <a href="#" class="font-medium text-[rgb(197,26,29)] hover:underline">ACCA T&C</a>.</label>
                    </div>
                    <div class="flex items-start">
                        <input id="examTerms" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-[rgb(197,26,29)] focus:ring-[rgb(177,23,26)]">
                        <label for="examTerms" class="ml-2 block text-sm text-gray-900">I accept the <a href="#" class="font-medium text-[rgb(197,26,29)] hover:underline">Exam T&C</a>.</label>
                    </div>
                     <p id="termsError" class="text-red-500 text-xs mt-1 hidden">You must accept all terms and conditions.</p>
                </div>
                <div class="mt-8 flex justify-between">
                    <button id="prevStep2" class="px-6 py-2 bg-slate-200 text-slate-800 font-semibold rounded-md hover:bg-slate-300 transition">Back</button>
                    <button id="nextStep2" class="px-6 py-2 bg-[rgb(197,26,29)] text-white font-semibold rounded-md hover:bg-[rgb(177,23,26)] transition">Next</button>
                </div>
            </div>

            <div id="step3" class="modal-step">
                <h2 class="text-2xl font-bold mb-4">You are all set!</h2>
                <p class="mb-6 text-slate-600">Click the button below to start your exam when you are ready. Good luck!</p>
                <div class="mt-8 flex justify-between items-center">
                    <button id="prevStep3" class="px-6 py-2 bg-slate-200 text-slate-800 font-semibold rounded-md hover:bg-slate-300 transition">Back</button>
                    <button id="finalStartBtn" class="flex w-1/2 justify-center rounded-md bg-[rgb(197,26,29)] px-3 py-3 text-sm font-semibold leading-6 text-white shadow-sm hover:bg-[rgb(177,23,26)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[rgb(197,26,29)] transition-all duration-200">
                        Start Exam Now
                    </button>
                </div>
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
        
        // --- NEW MODAL LOGIC ---

        // Modal Elements
        const modal = document.getElementById('studentModal');
        const steps = document.querySelectorAll('.modal-step');
        let currentStep = 0;

        const studentName = document.getElementById('studentName');
        const studentEmail = document.getElementById('studentEmail');
        const accaId = document.getElementById('accaId');

        const nameError = document.getElementById('nameError');
        const emailError = document.getElementById('emailError');
        const idError = document.getElementById('idError');
        const termsError = document.getElementById('termsError');
        
        const psbTerms = document.getElementById('psbTerms');
        const accaTerms = document.getElementById('accaTerms');
        const examTerms = document.getElementById('examTerms');

        // Function to switch between modal steps
        function showStep(stepIndex) {
            steps.forEach((step, index) => {
                step.classList.toggle('active', index === stepIndex);
            });
            currentStep = stepIndex;
        }

        // Validate Step 1: Student Details
        function validateStep1() {
            let isValid = true;
            // Basic validation: check if fields are empty
            if (studentName.value.trim() === '') {
                nameError.classList.remove('hidden');
                isValid = false;
            } else {
                nameError.classList.add('hidden');
            }
            // Basic email validation
            if (!/^\S+@\S+\.\S+$/.test(studentEmail.value)) {
                emailError.classList.remove('hidden');
                isValid = false;
            } else {
                emailError.classList.add('hidden');
            }
            if (accaId.value.trim() === '') {
                idError.classList.remove('hidden');
                isValid = false;
            } else {
                idError.classList.add('hidden');
            }
            return isValid;
        }

        // Validate Step 2: Terms & Conditions
        function validateStep2() {
            const isValid = psbTerms.checked && accaTerms.checked && examTerms.checked;
            if (!isValid) {
                termsError.classList.remove('hidden');
            } else {
                termsError.classList.add('hidden');
            }
            return isValid;
        }

        // Show the modal when the main start button is clicked
        startButton.addEventListener('click', () => {
            modal.classList.add('visible');
        });

        // Navigation button event listeners
        document.getElementById('nextStep1').addEventListener('click', () => {
            if (validateStep1()) {
                showStep(1);
            }
        });

        document.getElementById('prevStep2').addEventListener('click', () => {
            showStep(0);
        });

        document.getElementById('nextStep2').addEventListener('click', () => {
            if (validateStep2()) {
                showStep(2);
            }
        });
        
        document.getElementById('prevStep3').addEventListener('click', () => {
            showStep(1);
        });

        // Final Start Button - The redirection logic
        document.getElementById('finalStartBtn').addEventListener('click', () => {
            const selectedSubject = subjectSelect.value;
            if (selectedSubject) {
                const examPageUrl = `exam_${selectedSubject.toLowerCase()}.php`;
                alert(`Redirecting you to the ${selectedSubject} exam...`);
                window.location.href = examPageUrl;
            }
        });
        
        // Close modal if overlay is clicked
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.classList.remove('visible');
                // Optional: reset to first step when closing
                showStep(0); 
            }
        });

    </script>
</body>
</html>