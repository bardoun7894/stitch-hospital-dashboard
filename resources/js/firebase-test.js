// Test Firebase connection
import { db, auth, analytics } from './firebase';
import { collection, getDocs } from 'firebase/firestore';

console.log('🔥 Firebase initialized successfully!');
console.log('📊 Analytics:', analytics ? 'Active' : 'Inactive');
console.log('🔐 Auth:', auth ? 'Ready' : 'Not available');
console.log('💾 Firestore:', db ? 'Connected' : 'Not available');

// Test Firestore connection by fetching clinics
if (db) {
    console.log('🏥 Testing Firestore connection...');

    const clinicsRef = collection(db, 'clinics');
    getDocs(clinicsRef)
        .then((snapshot) => {
            console.log('✅ Firestore connection successful!');
            console.log(`📋 Found ${snapshot.size} clinics in database`);

            snapshot.forEach((doc) => {
                const data = doc.data();
                console.log(`  - ${doc.id}: ${data.name_en || data.name || 'Unnamed'}`);
            });
        })
        .catch((error) => {
            console.error('❌ Firestore connection error:', error);
        });
}

// Make functions available in browser console for testing
window.firebaseTest = {
    db,
    auth,
    analytics,
    testConnection: async () => {
        try {
            const clinicsRef = collection(db, 'clinics');
            const snapshot = await getDocs(clinicsRef);
            console.log(`✅ Successfully fetched ${snapshot.size} clinics`);
            return snapshot.size;
        } catch (error) {
            console.error('❌ Error:', error);
            throw error;
        }
    }
};

console.log('💡 Tip: Use window.firebaseTest.testConnection() to test Firestore connection');
