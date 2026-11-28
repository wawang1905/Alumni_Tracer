<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

// ── Public Routes (Unauthenticated) ─────────────────────────────────────────
$routes->get('/', 'LoginController::index');

$routes->post('auth/check-signup-record', 'Auth::checkSignupRecord');
$routes->post('auth/send-otp', 'Auth::sendEmailOtp');
$routes->post('auth/verify-otp', 'Auth::verifyEmailOtp');
$routes->get('auth/otp-status', 'Auth::getOtpStatus');
$routes->post('auth/signup', 'Auth::signup');
$routes->post('auth/login', 'Auth::login');
$routes->post('auth/logout', 'Auth::logout');
$routes->get('auth/session', 'Auth::sessionStatus');
$routes->post('auth/refresh', 'Auth::refreshSession');

// ── Authenticated Routes (All Logged-In Roles) ──────────────────────────────
$routes->group('', ['filter' => 'auth'], static function (RouteCollection $routes): void {
    $routes->get('auth/me', 'Auth::me');

    // Activity Feed & Logging
    $routes->get('activity/recent', 'Activity::getRecent');
    $routes->get('activity/stats', 'Activity::getStats');
    $routes->get('activity/list', 'Activity::getActivities');
    $routes->post('activity/log', 'Activity::log');
    $routes->post('activity/delete', 'Activity::delete');
    $routes->get('activity/export', 'Activity::export');

    // Events Read Endpoints (Alumni, Coordinator, Staff)
    $routes->get('events/list', 'EventController::list');
    $routes->get('events/get/(:num)', 'EventController::get/$1');
    $routes->get('events/upcoming', 'EventController::getUpcoming');
    $routes->get('events/past', 'EventController::getPast');
    $routes->get('events/statistics', 'EventController::getStatistics');

    // News Read Endpoints (Alumni, Coordinator, Staff)
    $routes->get('alumni/news', 'AlumniController::news');
    $routes->get('news/list', 'NewsController::list');

    // Survey Read & Participation Endpoints
    $routes->get('surveys/list', 'SurveyController::list');
    $routes->post('surveys/(:num)/responses', 'SurveyController::submitResponse/$1');
    $routes->get('surveys/(:num)/responses', 'SurveyController::listResponses/$1');
    $routes->get('surveys/responses/(:num)', 'SurveyController::getResponse/$1');
    $routes->get('surveys/(:num)/statistics', 'SurveyController::statistics/$1');
    $routes->get('surveys/(:num)/batch-statistics', 'SurveyController::batchStatistics/$1');

    // Shared Profile Picture Viewer (supports ?alumni_id= for Staff/Coordinator & own session for Alumni)
    $routes->get('alumni/profile-picture', 'AlumniController::serveProfilePicture');

    // Shared CSS/JS Assets for Authenticated Views
    $routes->get('staff/StaffCommon.css', 'StaffController::commonCss');
    $routes->get('staff/StaffCommon.js', 'StaffController::commonJs');
    $routes->get('coordinator/CoordinatorCommon.css', 'CoordinatorController::commonCss');
    $routes->get('coordinator/CoordinatorCommon.js', 'CoordinatorController::commonJs');
    $routes->get('developer/DeveloperCommon.css', 'DeveloperController::commonCss');
    $routes->get('developer/DeveloperCommon.js', 'DeveloperController::commonJs');
    $routes->get('alumni/AlumniCommon.css', 'AlumniController::commonCss');
    $routes->get('alumni/AlumniCommon.js', 'AlumniController::commonJs');
});

// ── Staff & Coordinator Shared Protected Routes ─────────────────────────────
$routes->group('', ['filter' => 'role:staff,coordinator'], static function (RouteCollection $routes): void {
    $routes->get('staff/colleges-data', 'StaffController::getCollegesData');
    $routes->get('staff/departments-data', 'StaffController::getDepartmentsData');

    $routes->post('reports/generate', 'StaffController::generate');
    $routes->post('reports/preview-count', 'StaffController::previewCount');

    $routes->get('staff/payments-data', 'StaffController::getPaymentsData');
    $routes->post('staff/payments/create', 'StaffController::createPayment');
    $routes->post('staff/payments/update', 'StaffController::updatePayment');
    $routes->post('staff/payments/update-status', 'StaffController::updatePaymentStatus');
    $routes->get('staff/payments/export', 'StaffController::exportPayments');
    $routes->get('staff/payments/export-excel', 'StaffController::exportPaymentExcel');
    $routes->match(['GET', 'POST'], 'staff/payments/export-excel/check', 'StaffController::checkPaymentExcelCount');

    // Event Management
    $routes->post('events/create', 'EventController::create');
    $routes->put('events/update/(:num)', 'EventController::update/$1');
    $routes->delete('events/delete/(:num)', 'EventController::delete/$1');

    // Survey Response Review & Answer Images
    $routes->post('surveys/responses/(:num)/review', 'SurveyController::reviewResponse/$1');
    $routes->get('surveys/answers/(:num)/image', 'SurveyController::answerImage/$1');
});

// ── Staff / Head Admin Protected Routes ─────────────────────────────────────
$routes->group('', ['filter' => 'role:staff'], static function (RouteCollection $routes): void {
    // Staff HTML Pages
    $routes->get('staff/Dashboard.html', 'StaffController::dashboard');
    $routes->get('staff/AlumniManagement.html', 'StaffController::alumniManagement');
    $routes->get('staff/AlumniBatchDetails.html', 'StaffController::alumniBatchDetails');
    $routes->get('staff/AlumniCollegeDetails.html', 'StaffController::alumniCollegeDetails');
    $routes->get('staff/AlumniCourseDetails.html', 'StaffController::alumniCourseDetails');
    $routes->get('staff/Events.html', 'StaffController::events');
    $routes->get('staff/News.html', 'StaffController::news');
    $routes->get('staff/Reports.html', 'StaffController::reports');
    $routes->get('staff/Settings.html', 'StaffController::settings');
    $routes->get('staff/Survey.html', 'StaffController::surveys');
    $routes->get('staff/Surveys.html', 'StaffController::surveys');
    $routes->get('staff/SurveyCreate.html', 'StaffController::surveyCreate');
    $routes->get('staff/SurveyResponses.html', 'StaffController::surveyResponses');
    $routes->get('staff/SurveyResponseDetails.html', 'StaffController::surveyResponseDetails');
    $routes->get('staff/SurveyAllResponsesDetails.html', 'StaffController::surveyAllResponsesDetails');
    $routes->get('staff/Coordinators.html', 'StaffController::coordinators');
    $routes->get('staff/Colleges.html', 'StaffController::colleges');
    $routes->get('staff/Departments.html', 'StaffController::departments');
    $routes->get('staff/Payments.html', 'StaffController::payments');
    $routes->get('staff/dashboard', 'StaffDashboard::index');
    $routes->get('staff/getDashboardData', 'StaffDashboard::getDashboardData');

    // Staff Management APIs
    $routes->post('staff/colleges/create', 'StaffController::createCollege');
    $routes->post('staff/departments/create', 'StaffController::createDepartment');
    $routes->get('staff/coordinators', 'StaffController::getCoordinators');
    $routes->post('staff/coordinators/create', 'StaffController::createCoordinator');
    $routes->get('staff/assignable-accounts', 'StaffController::getAssignableAccounts');
    $routes->post('staff/coordinators/assign', 'StaffController::assignCoordinator');

    // Alumni Management APIs (Staff)
    $routes->get('alumni/list', 'AlumniController::list');
    $routes->get('alumni/batches', 'AlumniController::batches');
    $routes->post('alumni/batches/create', 'AlumniController::createBatch');
    $routes->delete('alumni/batches/delete/(:num)', 'AlumniController::deleteBatch/$1');
    $routes->get('alumni/batches/colleges-template', 'AlumniController::downloadBatchCollegesTemplate');
    $routes->get('alumni/batches/departments-template', 'AlumniController::downloadBatchDepartmentsTemplate');
    $routes->get('alumni/batches/(:num)/structure', 'AlumniController::batchStructure/$1');
    $routes->get('alumni/batches/(:num)/catalog', 'AlumniController::batchCatalog/$1');
    $routes->post('alumni/batches/(:num)/colleges', 'AlumniController::addBatchCollege/$1');
    $routes->post('alumni/batches/(:num)/colleges/import/preview', 'AlumniController::previewBatchCollegesImport/$1');
    $routes->post('alumni/batches/(:num)/colleges/import', 'AlumniController::importBatchColleges/$1');
    $routes->post('alumni/batches/(:num)/colleges/(:num)/departments', 'AlumniController::addBatchDepartment/$1/$2');
    $routes->post('alumni/batches/(:num)/colleges/(:num)/departments/import/preview', 'AlumniController::previewBatchDepartmentsImport/$1/$2');
    $routes->post('alumni/batches/(:num)/colleges/(:num)/departments/import', 'AlumniController::importBatchDepartments/$1/$2');
    $routes->get('alumni/export', 'AlumniController::export');
    $routes->post('alumni/create', 'AlumniController::create');
    $routes->post('alumni/import', 'AlumniController::import');
    $routes->get('alumni/digital-id/(:num)', 'AlumniController::digitalId/$1');
    $routes->get('alumni/digital-id/(:num)/download', 'AlumniController::downloadDigitalId/$1');

    // News Management APIs (Staff)
    $routes->post('news/create', 'NewsController::create');
    $routes->post('news/update/(:num)', 'NewsController::update/$1');
    $routes->delete('news/delete/(:num)', 'NewsController::delete/$1');

    // Survey Administration APIs (Staff)
    $routes->post('surveys/create', 'SurveyController::create');
    $routes->post('surveys/(:num)/update', 'SurveyController::update/$1');
    $routes->post('surveys/(:num)/status', 'SurveyController::updateStatus/$1');
    $routes->post('surveys/(:num)/state', 'SurveyController::updateState/$1');
    $routes->post('surveys/(:num)/allow-multiple', 'SurveyController::updateAllowMultiple/$1');
    $routes->post('surveys/(:num)/next-batch', 'SurveyController::nextBatch/$1');
    $routes->delete('surveys/(:num)', 'SurveyController::delete/$1');
    $routes->post('surveys/(:num)/questions', 'SurveyController::addQuestion/$1');
    $routes->post('surveys/questions/(:num)/update', 'SurveyController::updateQuestion/$1');
    $routes->delete('surveys/questions/(:num)', 'SurveyController::deleteQuestion/$1');
});

// ── Coordinator Protected Routes ────────────────────────────────────────────
$routes->group('', ['filter' => 'role:coordinator'], static function (RouteCollection $routes): void {
    $routes->get('coordinator/Dashboard.html', 'CoordinatorController::dashboard');
    $routes->get('coordinator/Alumni.html', 'CoordinatorController::alumni');
    $routes->get('coordinator/Reports.html', 'CoordinatorController::reports');
    $routes->get('coordinator/Events.html', 'CoordinatorController::events');
    $routes->get('coordinator/SurveyResponses.html', 'CoordinatorController::surveyResponses');
    $routes->get('coordinator/SurveyResponseDetails.html', 'CoordinatorController::surveyResponseDetails');
    $routes->get('coordinator/alumni', 'CoordinatorController::getAlumni');
    $routes->get('coordinator/dashboard-data', 'CoordinatorController::dashboardData');
    $routes->get('coordinator/session', 'CoordinatorController::sessionInfo');
});

// ── Developer Protected Routes ──────────────────────────────────────────────
$routes->group('', ['filter' => 'role:developer'], static function (RouteCollection $routes): void {
    $routes->get('developer/Dashboard.html', 'DeveloperController::dashboard');
    $routes->get('developer/Overview.html', 'DeveloperController::overview');
    $routes->get('developer/UserManagement.html', 'DeveloperController::userManagement');
    $routes->get('developer/SystemSettings.html', 'DeveloperController::systemSettings');
    $routes->get('developer/SchoolInfo.html', 'DeveloperController::schoolInfo');
    $routes->get('developer/BackupRestore.html', 'DeveloperController::backupRestore');
    $routes->get('developer/SystemLogs.html', 'DeveloperController::systemLogs');
    $routes->get('developer/Announcements.html', 'DeveloperController::announcements');
    $routes->get('developer/EmailSMS.html', 'DeveloperController::emailSMS');
    $routes->get('developer/SurveyTemplates.html', 'DeveloperController::surveyTemplates');
    $routes->get('developer/Maintenance.html', 'DeveloperController::maintenance');
    $routes->get('developer/overview-data', 'DeveloperController::overviewData');
    $routes->get('developer/users', 'DeveloperController::users');
    $routes->post('developer/users', 'DeveloperController::saveUser');
    $routes->delete('developer/users/(:segment)', 'DeveloperController::deleteUser/$1');
    $routes->get('developer/campuses', 'DeveloperController::campuses');
    $routes->post('developer/campuses', 'DeveloperController::saveCampus');
    $routes->delete('developer/campuses/(:num)', 'DeveloperController::deleteCampus/$1');
    $routes->get('developer/backups', 'DeveloperController::listBackups');
    $routes->post('developer/backups/create', 'DeveloperController::createBackup');
    $routes->get('developer/backups/download/(:segment)', 'DeveloperController::downloadBackup/$1');
    $routes->delete('developer/backups/(:segment)', 'DeveloperController::deleteBackup/$1');
    $routes->post('developer/backups/restore', 'DeveloperController::restoreBackup');
});

// ── Alumni Protected Routes ─────────────────────────────────────────────────
$routes->group('', ['filter' => 'role:alumni'], static function (RouteCollection $routes): void {
    $routes->get('alumni/Main.html', 'AlumniController::main');
    $routes->get('alumni/Events.html', 'AlumniController::eventsPage');
    $routes->get('alumni/News.html', 'AlumniController::newsPage');
    $routes->get('alumni/Surveys.html', 'AlumniController::surveysPage');
    $routes->get('alumni/SurveyRespond.html', 'AlumniController::surveyRespondPage');
    $routes->get('alumni/Profile.html', 'AlumniController::profilePage');
    $routes->get('alumni/profile', 'AlumniController::profile');
    $routes->get('alumni/my-digital-id', 'AlumniController::myDigitalId');
    $routes->post('alumni/update', 'AlumniController::update');
    $routes->post('alumni/complete-personal-info', 'AlumniController::completePersonalInfo');
    $routes->post('alumni/profile-picture', 'AlumniController::uploadProfilePicture');
});
