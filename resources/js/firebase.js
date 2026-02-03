// Import the functions you need from the SDKs you need
import { initializeApp } from "firebase/app";
import { getAnalytics } from "firebase/analytics";
import { getAuth } from "firebase/auth";
import { getFirestore } from "firebase/firestore";

// Your web app's Firebase configuration
const firebaseConfig = {
  apiKey: import.meta.env.VITE_FIREBASE_API_KEY || "AIzaSyABfIj3oOZ1o3sFqlNAR8gEMm8ey-KKJK8",
  authDomain: import.meta.env.VITE_FIREBASE_AUTH_DOMAIN || "clinicqu-1e93c.firebaseapp.com",
  projectId: import.meta.env.VITE_FIREBASE_PROJECT_ID || "clinicqu-1e93c",
  storageBucket: import.meta.env.VITE_FIREBASE_STORAGE_BUCKET || "clinicqu-1e93c.firebasestorage.app",
  messagingSenderId: import.meta.env.VITE_FIREBASE_MESSAGING_SENDER_ID || "79990018882",
  appId: import.meta.env.VITE_FIREBASE_APP_ID || "1:79990018882:web:00692e031e9f25dfa3aedb",
  measurementId: import.meta.env.VITE_FIREBASE_MEASUREMENT_ID || "G-SZPJ3636RN"
};

// Initialize Firebase
const app = initializeApp(firebaseConfig);

// Initialize Firebase services
const analytics = getAnalytics(app);
const auth = getAuth(app);
const db = getFirestore(app);

// Export for use in other files
export { app, analytics, auth, db };
