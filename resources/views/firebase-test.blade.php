<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Firebase Test - Clinic App</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body {
            font-family: system-ui, -apple-system, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 2rem;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .card {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }
        .status-badge {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 600;
        }
        .status-success { background: #10b981; color: white; }
        .status-error { background: #ef4444; color: white; }
        .status-warning { background: #f59e0b; color: white; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; }
        .clinic-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 8px;
        }
        .doctor-card {
            background: #f3f4f6;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 0.5rem;
        }
        pre {
            background: #1f2937;
            color: #10b981;
            padding: 1rem;
            border-radius: 8px;
            overflow-x: auto;
            font-size: 0.875rem;
        }
        h1 { color: white; margin-bottom: 2rem; font-size: 2.5rem; }
        h2 { margin-bottom: 1rem; color: #1f2937; }
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f4f6;
            border-top-color: #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔥 Firebase Connection Test</h1>

        <!-- Status Card -->
        <div class="card">
            <h2>Backend Connection Status</h2>
            <div id="backend-status">
                <div class="loading"></div> Loading...
            </div>
        </div>

        <!-- Clinics from Firebase -->
        <div class="card">
            <h2>🏥 Clinics from Firestore</h2>
            <div id="clinics-container">
                <div class="loading"></div> Loading clinics...
            </div>
        </div>

        <!-- Doctors from Firebase -->
        <div class="card">
            <h2>👨‍⚕️ Doctors from Database</h2>
            <div id="doctors-container">
                <div class="loading"></div> Loading doctors...
            </div>
        </div>

        <!-- Frontend Firebase Test -->
        <div class="card">
            <h2>🌐 Frontend Firebase SDK Test</h2>
            <p>Open browser console (F12) to see Firebase initialization logs</p>
            <button onclick="testFrontendFirebase()" class="status-badge status-success" style="cursor: pointer; border: none;">
                Test Firestore Connection
            </button>
            <div id="frontend-result" style="margin-top: 1rem;"></div>
        </div>

        <!-- Raw Data -->
        <div class="card">
            <h2>📊 Raw JSON Data</h2>
            <pre id="raw-data">Loading...</pre>
        </div>
    </div>

    <script>
        // Fetch backend data
        async function loadBackendData() {
            try {
                const response = await fetch('/firebase-test');
                const data = await response.json();

                // Update status
                const statusHtml = `
                    <p><strong>Status:</strong> <span class="status-badge status-success">${data.status}</span></p>
                    <p><strong>Firestore Connected:</strong> <span class="status-badge ${data.firestore_connected ? 'status-success' : 'status-error'}">${data.firestore_connected ? 'YES ✅' : 'NO ❌'}</span></p>
                    <p><strong>Message:</strong> ${data.message}</p>
                `;
                document.getElementById('backend-status').innerHTML = statusHtml;

                // Update clinics
                const clinicsHtml = `
                    <p style="margin-bottom: 1rem;"><strong>Found ${data.data.clinics_count} clinics</strong></p>
                    <div class="grid">
                        ${data.data.clinics.map(clinic => `
                            <div class="clinic-card">
                                <h3 style="margin: 0 0 0.5rem 0;">${clinic.name}</h3>
                                <p><strong>ID:</strong> ${clinic.id}</p>
                                <p><strong>Doctors on Duty:</strong> ${clinic.doctors_on_duty}</p>
                                <p><strong>Patients Waiting:</strong> ${clinic.patients_waiting}</p>
                                <p><strong>Avg Wait:</strong> ${clinic.avg_wait}</p>
                                <p><strong>Status:</strong> ${clinic.status}</p>
                            </div>
                        `).join('')}
                    </div>
                `;
                document.getElementById('clinics-container').innerHTML = clinicsHtml;

                // Update doctors
                const doctorsHtml = `
                    <p style="margin-bottom: 1rem;"><strong>Found ${data.data.doctors_count} doctors</strong></p>
                    ${data.data.doctors.map(doctor => `
                        <div class="doctor-card">
                            <strong>${doctor.name}</strong> - ${doctor.specialty}
                            <span class="status-badge ${doctor.status === 'On Duty' ? 'status-success' : 'status-warning'}" style="float: right; font-size: 0.75rem; padding: 0.25rem 0.75rem;">
                                ${doctor.status}
                            </span>
                            <br>
                            <small>Clinic: ${doctor.clinic}</small>
                        </div>
                    `).join('')}
                `;
                document.getElementById('doctors-container').innerHTML = doctorsHtml;

                // Update raw data
                document.getElementById('raw-data').textContent = JSON.stringify(data, null, 2);

            } catch (error) {
                document.getElementById('backend-status').innerHTML = `
                    <span class="status-badge status-error">ERROR: ${error.message}</span>
                `;
            }
        }

        // Test frontend Firebase
        async function testFrontendFirebase() {
            const resultDiv = document.getElementById('frontend-result');
            resultDiv.innerHTML = '<div class="loading"></div> Testing...';

            try {
                if (window.firebaseTest && window.firebaseTest.testConnection) {
                    const count = await window.firebaseTest.testConnection();
                    resultDiv.innerHTML = `
                        <div class="status-badge status-success">
                            ✅ Frontend Firebase connected! Found ${count} clinics
                        </div>
                    `;
                } else {
                    resultDiv.innerHTML = `
                        <div class="status-badge status-warning">
                            ⚠️ Firebase test function not available. Check console for details.
                        </div>
                    `;
                }
            } catch (error) {
                resultDiv.innerHTML = `
                    <div class="status-badge status-error">
                        ❌ Error: ${error.message}
                    </div>
                `;
            }
        }

        // Load data on page load
        loadBackendData();

        // Auto-refresh every 10 seconds
        setInterval(loadBackendData, 10000);
    </script>
</body>
</html>
