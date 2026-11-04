// Debug script to check profile page functionality
console.log('=== PROFILE PAGE DEBUG ===');
console.log('1. Checking DOM elements...');

// Check if all required elements exist
const requiredElements = {
    profileAlumniId: document.getElementById('profileAlumniId'),
    profileFirstName: document.getElementById('profileFirstName'),
    profileLastName: document.getElementById('profileLastName'),
    profileEmail: document.getElementById('profileEmail'),
    profileGraduationYear: document.getElementById('profileGraduationYear'),
    profileCollege: document.getElementById('profileCollege'),
    profileCourse: document.getElementById('profileCourse'),
    editPersonalBtn: document.getElementById('editPersonalBtn'),
    personalActions: document.getElementById('personalActions')
};

console.log('Required elements found:', {
    profileAlumniId: !!requiredElements.profileAlumniId,
    profileFirstName: !!requiredElements.profileFirstName,
    profileLastName: !!requiredElements.profileLastName,
    profileEmail: !!requiredElements.profileEmail,
    profileGraduationYear: !!requiredElements.profileGraduationYear,
    profileCollege: !!requiredElements.profileCollege,
    profileCourse: !!requiredElements.profileCourse,
    editPersonalBtn: !!requiredElements.editPersonalBtn,
    personalActions: !!requiredElements.personalActions
});

console.log('2. Checking current values...');
console.log('Current form values:', {
    alumniId: requiredElements.profileAlumniId?.value || 'NOT_FOUND',
    firstName: requiredElements.profileFirstName?.value || 'NOT_FOUND',
    lastName: requiredElements.profileLastName?.value || 'NOT_FOUND',
    email: requiredElements.profileEmail?.value || 'NOT_FOUND',
    graduationYear: requiredElements.profileGraduationYear?.value || 'NOT_FOUND',
    college: requiredElements.profileCollege?.value || 'NOT_FOUND',
    course: requiredElements.profileCourse?.value || 'NOT_FOUND'
});

console.log('3. Checking functions...');
console.log('loadProfileData function:', typeof loadProfileData);
console.log('displayProfileData function:', typeof displayProfileData);
console.log('enablePersonalEdit function:', typeof enablePersonalEdit);
console.log('savePersonalInfo function:', typeof savePersonalInfo);

console.log('4. Checking global variables...');
console.log('isEditingPersonal:', typeof isEditingPersonal !== 'undefined' ? isEditingPersonal : 'UNDEFINED');
console.log('originalPersonalData:', typeof originalPersonalData !== 'undefined' ? 'defined' : 'UNDEFINED');

console.log('5. Checking network requests...');
// Test profile API call
fetch('/alumni/profile', {
    method: 'GET',
    credentials: 'same-origin',
    headers: {
        'Content-Type': 'application/json'
    }
})
.then(response => {
    console.log('Profile API response status:', response.status);
    return response.json();
})
.then(data => {
    console.log('Profile API response data:', data);
})
.catch(error => {
    console.error('Profile API error:', error);
});

console.log('=== END DEBUG ===');
