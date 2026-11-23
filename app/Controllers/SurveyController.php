<?php

namespace App\Controllers;

use App\Models\AlumniModel;
use App\Models\AnswerModel;
use App\Models\CategoryModel;
use App\Models\ChoiceModel;
use App\Models\QuestionModel;
use App\Models\ResponseModel;
use App\Models\SurveyModel;
use App\Models\UserAccountModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Files\File;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\HTTP\Files\UploadedFile;
use Config\Database;

class SurveyController extends BaseController
{
    private SurveyModel $surveyModel;
    private CategoryModel $categoryModel;
    private QuestionModel $questionModel;
    private ChoiceModel $choiceModel;
    private ResponseModel $responseModel;
    private AnswerModel $answerModel;
    private AlumniModel $alumniModel;
    private UserAccountModel $userAccountModel;
    private BaseConnection $db;

    public function __construct()
    {
        $this->surveyModel = new SurveyModel();
        $this->categoryModel = new CategoryModel();
        $this->questionModel = new QuestionModel();
        $this->choiceModel = new ChoiceModel();
        $this->responseModel = new ResponseModel();
        $this->answerModel = new AnswerModel();
        $this->alumniModel = new AlumniModel();
        $this->userAccountModel = new UserAccountModel();
        $this->db = Database::connect();
    }

    public function list(): ResponseInterface
    {
        if (! session()->has('auth_user')) {
            return $this->response->setStatusCode(401)->setJSON([
                'success' => false,
                'message' => 'Unauthorized.',
            ]);
        }

        return $this->response->setJSON([
            'success' => true,
            'data' => $this->getStructuredSurveys(),
        ]);
    }

    public function create(): ResponseInterface
    {
        if (! $this->isStaffAuthorized()) {
            return $this->response->setStatusCode(403)->setJSON([
                'success' => false,
                'message' => 'Forbidden.',
            ]);
        }

        $raw = $this->getRequestData();
        $title = trim((string) ($raw['title'] ?? ''));
        $description = trim((string) ($raw['description'] ?? ''));
        $categories = $this->normalizeCategoriesInput($raw['categories'] ?? []);
        $openDatetime = $this->normalizeSurveyDatetime($raw['open_datetime'] ?? null);
        $closeDatetime = $this->normalizeSurveyDatetime($raw['close_datetime'] ?? null);
        $scheduledFor = $this->normalizeSurveyDatetime($raw['scheduled_for'] ?? null);

        if ($scheduledFor !== null && strtotime($scheduledFor) <= time()) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'Scheduled time must be in the future.',
                'errors' => ['Scheduled time must be in the future'],
            ]);
        }

        $validationErrors = $this->validateSurveyWizardPayload($title, $description, $openDatetime, $closeDatetime, $categories);
        if ($validationErrors !== []) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => $validationErrors[0],
                'errors' => $validationErrors,
            ]);
        }

        $this->db->transStart();

        $this->surveyModel->insert([
            'title' => $title,
            'description' => $description,
            'status' => 'open',
            'allow_multiple_responses' => false,
            'current_batch' => 1,
            'open_datetime' => $openDatetime,
            'close_datetime' => $closeDatetime,
            'survey_state' => 'not posted',
        ]);
        $surveyId = (int) $this->surveyModel->getInsertID();

        $this->syncSurveyStructure($surveyId, $categories);

        $this->db->transComplete();

        if (! $this->db->transStatus()) {
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'message' => 'Unable to create survey right now.',
            ]);
        }

        $created = $this->surveyModel->find($surveyId);

        return $this->response->setStatusCode(201)->setJSON([
            'success' => true,
            'message' => 'Survey created successfully.',
            'data' => $this->formatSurvey(
                is_array($created) ? $created : [
                    'id' => $surveyId,
                    'title' => $title,
                    'description' => $description,
                    'created_at' => date('Y-m-d H:i:s'),
                    'status' => 'open',
                    'allow_multiple_responses' => false,
                    'current_batch' => 1,
                    'open_datetime' => $openDatetime,
                    'close_datetime' => $closeDatetime,
                    'survey_state' => 'not posted',
                ],
                $this->getCategoriesForSurvey($surveyId)
            ),
        ]);
    }

    public function delete(int $surveyId): ResponseInterface
    {
        if (! $this->isStaffAuthorized()) {
            return $this->response->setStatusCode(403)->setJSON([
                'success' => false,
                'message' => 'Forbidden.',
            ]);
        }

        $survey = $this->surveyModel->find($surveyId);
        if (! is_array($survey)) {
            return $this->response->setStatusCode(404)->setJSON([
                'success' => false,
                'message' => 'Survey not found.',
            ]);
        }

        $this->db->transStart();
        $this->deleteSurveyStructure($surveyId);
        $this->responseModel->where('survey_id', $surveyId)->delete();
        $this->surveyModel->delete($surveyId);
        $this->db->transComplete();

        if (! $this->db->transStatus()) {
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'message' => 'Unable to delete survey right now.',
            ]);
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Survey deleted successfully.',
        ]);
    }

    public function update(int $surveyId): ResponseInterface
    {
        if (! $this->isStaffAuthorized()) {
            return $this->response->setStatusCode(403)->setJSON([
                'success' => false,
                'message' => 'Forbidden.',
            ]);
        }

        $survey = $this->surveyModel->find($surveyId);
        if (! is_array($survey)) {
            return $this->response->setStatusCode(404)->setJSON([
                'success' => false,
                'message' => 'Survey not found.',
            ]);
        }

        $raw = $this->getRequestData();
        $title = trim((string) ($raw['title'] ?? ''));
        if ($title === '') {
            $title = trim((string) ($survey['title'] ?? ''));
        }
        $description = trim((string) ($raw['description'] ?? (string) ($survey['description'] ?? '')));
        $openDatetime = array_key_exists('open_datetime', $raw) && $raw['open_datetime'] !== null && trim((string) $raw['open_datetime']) !== ''
            ? $this->normalizeSurveyDatetime($raw['open_datetime'])
            : $this->normalizeSurveyDatetime($survey['open_datetime'] ?? null);
        $closeDatetime = array_key_exists('close_datetime', $raw) && $raw['close_datetime'] !== null && trim((string) $raw['close_datetime']) !== ''
            ? $this->normalizeSurveyDatetime($raw['close_datetime'])
            : $this->normalizeSurveyDatetime($survey['close_datetime'] ?? null);
        $categoriesProvided = array_key_exists('categories', $raw);
        $categories = $categoriesProvided
            ? $this->normalizeCategoriesInput($raw['categories'])
            : $this->getCategoriesForSurvey($surveyId);

        if ($title === '') {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'Survey title is required.',
                'errors' => ['Survey name is required'],
            ]);
        }

        $scheduledFor = array_key_exists('scheduled_for', $raw)
            ? $this->normalizeSurveyDatetime($raw['scheduled_for'])
            : null;
        if ($scheduledFor !== null && strtotime($scheduledFor) <= time()) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'Scheduled time must be in the future.',
                'errors' => ['Scheduled time must be in the future'],
            ]);
        }

        if ($categoriesProvided) {
            $validationErrors = $this->validateSurveyWizardPayload($title, $description, $openDatetime, $closeDatetime, $categories, true);
            if ($validationErrors !== []) {
                return $this->response->setStatusCode(422)->setJSON([
                    'success' => false,
                    'message' => $validationErrors[0],
                    'errors' => $validationErrors,
                ]);
            }
        } else {
            if ($openDatetime !== null && $closeDatetime !== null && strtotime($closeDatetime) < strtotime($openDatetime)) {
                return $this->response->setStatusCode(422)->setJSON([
                    'success' => false,
                    'message' => 'Close date and time must be later than the open date and time.',
                    'errors' => ['Closing date must be after opening date'],
                ]);
            }
        }

        $this->db->transStart();

        $this->surveyModel->update($surveyId, [
            'title' => $title,
            'description' => $description,
            'open_datetime' => $openDatetime,
            'close_datetime' => $closeDatetime,
        ]);

        if ($categoriesProvided) {
            $this->syncSurveyStructure($surveyId, $categories);
        }

        $this->db->transComplete();

        if (! $this->db->transStatus()) {
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'message' => 'Unable to update survey right now.',
            ]);
        }

        $updated = $this->surveyModel->find($surveyId);

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Survey updated successfully.',
            'data' => $this->formatSurvey(
                is_array($updated) ? $updated : $survey,
                $this->getCategoriesForSurvey($surveyId)
            ),
        ]);
    }

    public function nextBatch(int $surveyId): ResponseInterface
    {
        if (! $this->isStaffAuthorized()) {
            return $this->response->setStatusCode(403)->setJSON([
                'success' => false,
                'message' => 'Forbidden.',
            ]);
        }

        $survey = $this->surveyModel->find($surveyId);
        if (! is_array($survey)) {
            return $this->response->setStatusCode(404)->setJSON([
                'success' => false,
                'message' => 'Survey not found.',
            ]);
        }

        $batch = max(1, (int) ($survey['current_batch'] ?? 1));

        $this->surveyModel->update($surveyId, [
            'current_batch' => $batch + 1,
        ]);

        $updated = $this->surveyModel->find($surveyId);

        return $this->response->setJSON([
            'success' => true,
            'message' => 'New survey cycle opened. Alumni can submit for this batch.',
            'data' => $this->formatSurvey(
                is_array($updated) ? $updated : $survey,
                $this->getCategoriesForSurvey($surveyId)
            ),
        ]);
    }

    public function updateAllowMultiple(int $surveyId): ResponseInterface
    {
        if (! $this->isStaffAuthorized()) {
            return $this->response->setStatusCode(403)->setJSON([
                'success' => false,
                'message' => 'Forbidden.',
            ]);
        }

        $survey = $this->surveyModel->find($surveyId);
        if (! is_array($survey)) {
            return $this->response->setStatusCode(404)->setJSON([
                'success' => false,
                'message' => 'Survey not found.',
            ]);
        }

        $raw = $this->getRequestData();
        $allowed = null;
        if (array_key_exists('allowed', $raw)) {
            $allowed = filter_var($raw['allowed'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        } elseif (array_key_exists('allow_multiple_responses', $raw)) {
            $allowed = filter_var($raw['allow_multiple_responses'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }

        if ($allowed === null) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'Send "allowed": true/false (or allow_multiple_responses).',
            ]);
        }

        $this->surveyModel->update($surveyId, [
            'allow_multiple_responses' => (bool) $allowed,
        ]);

        $updated = $this->surveyModel->find($surveyId);

        return $this->response->setJSON([
            'success' => true,
            'message' => $allowed
                ? 'Multiple responses enabled: alumni can submit more than once while the survey is accepting responses.'
                : 'Multiple responses disabled: each alumni can submit only once.',
            'data' => $this->formatSurvey(
                is_array($updated) ? $updated : $survey,
                $this->getCategoriesForSurvey($surveyId)
            ),
        ]);
    }

    public function updateStatus(int $surveyId): ResponseInterface
    {
        if (! $this->isStaffAuthorized()) {
            return $this->response->setStatusCode(403)->setJSON([
                'success' => false,
                'message' => 'Forbidden.',
            ]);
        }

        $survey = $this->surveyModel->find($surveyId);
        if (! is_array($survey)) {
            return $this->response->setStatusCode(404)->setJSON([
                'success' => false,
                'message' => 'Survey not found.',
            ]);
        }

        $raw = $this->getRequestData();
        $status = strtolower(trim((string) ($raw['status'] ?? '')));
        if (! in_array($status, ['open', 'closed'], true)) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'Status must be "open" or "closed".',
            ]);
        }

        $this->surveyModel->update($surveyId, ['status' => $status]);
        $updated = $this->surveyModel->find($surveyId);

        return $this->response->setJSON([
            'success' => true,
            'message' => $status === 'closed' ? 'Survey closed to alumni responses.' : 'Survey reopened.',
            'data' => $this->formatSurvey(
                is_array($updated) ? $updated : $survey,
                $this->getCategoriesForSurvey($surveyId)
            ),
        ]);
    }

    public function updateState(int $surveyId): ResponseInterface
    {
        if (! $this->isStaffAuthorized()) {
            return $this->response->setStatusCode(403)->setJSON([
                'success' => false,
                'message' => 'Forbidden.',
            ]);
        }

        $survey = $this->surveyModel->find($surveyId);
        if (! is_array($survey)) {
            return $this->response->setStatusCode(404)->setJSON([
                'success' => false,
                'message' => 'Survey not found.',
            ]);
        }

        $raw = $this->getRequestData();
        $state = strtolower(trim((string) ($raw['survey_state'] ?? $raw['state'] ?? '')));
        if (! in_array($state, ['posted', 'not posted'], true)) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'Survey state must be "posted" or "not posted".',
                'errors' => ['Survey state must be "posted" or "not posted".'],
            ]);
        }

        $publishMethod = strtolower(trim((string) ($raw['publish_method'] ?? '')));
        $scheduledFor = $this->normalizeSurveyDatetime($raw['scheduled_for'] ?? null);
        if ($publishMethod === 'scheduled') {
            if ($scheduledFor === null) {
                return $this->response->setStatusCode(422)->setJSON([
                    'success' => false,
                    'message' => 'Please select a publish date and time.',
                    'errors' => ['Please select a publish date and time.'],
                ]);
            }
            if (strtotime($scheduledFor) <= time()) {
                return $this->response->setStatusCode(422)->setJSON([
                    'success' => false,
                    'message' => 'Scheduled time must be in the future.',
                    'errors' => ['Scheduled time must be in the future'],
                ]);
            }
        }

        $openDatetime = $this->normalizeSurveyDatetime($survey['open_datetime'] ?? null);
        $closeDatetime = $this->normalizeSurveyDatetime($survey['close_datetime'] ?? null);
        if ($state === 'posted' && ($openDatetime === null || $closeDatetime === null)) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'Set both open time and close time before posting this survey.',
                'errors' => ['Set both open time and close time before posting this survey.'],
            ]);
        }

        $categories = $this->getCategoriesForSurvey($surveyId);
        $totalQuestions = 0;
        foreach ($categories as $category) {
            $totalQuestions += count($category['questions'] ?? []);
        }
        if ($state === 'posted' && $totalQuestions < 1) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'At least one question is required',
                'errors' => ['At least one question is required'],
            ]);
        }

        $this->surveyModel->update($surveyId, ['survey_state' => $state]);
        $updated = $this->surveyModel->find($surveyId);

        return $this->response->setJSON([
            'success' => true,
            'message' => $state === 'posted'
                ? 'Survey posted successfully.'
                : 'Survey marked as not posted.',
            'data' => $this->formatSurvey(
                is_array($updated) ? $updated : $survey,
                $this->getCategoriesForSurvey($surveyId)
            ),
        ]);
    }

    public function addQuestion(int $surveyId): ResponseInterface
    {
        if (! $this->isStaffAuthorized()) {
            return $this->response->setStatusCode(403)->setJSON([
                'success' => false,
                'message' => 'Forbidden.',
            ]);
        }

        $survey = $this->surveyModel->find($surveyId);
        if (! is_array($survey)) {
            return $this->response->setStatusCode(404)->setJSON([
                'success' => false,
                'message' => 'Survey not found.',
            ]);
        }

        $raw = $this->getRequestData();
        $text = trim((string) ($raw['text'] ?? ''));
        $type = trim((string) ($raw['type'] ?? ''));
        $options = $this->normalizeQuestionOptions($raw['options'] ?? []);
        $categoryId = $this->resolveCategoryForSurvey(
            $surveyId,
            isset($raw['category_id']) ? (int) $raw['category_id'] : null,
            trim((string) ($raw['category_name'] ?? ''))
        );

        if ($text === '') {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'Question text is required.',
            ]);
        }

        if (! in_array($type, ['text', 'multiple_choice', 'rating', 'image'], true)) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'Invalid question type.',
            ]);
        }

        if ($type === 'multiple_choice' && count($options) < 2) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'Multiple choice questions need at least 2 options.',
            ]);
        }

        $this->db->transStart();

        $this->questionModel->insert([
            'category_id' => $categoryId,
            'question_text' => $text,
            'question_type' => $type,
            'required' => filter_var($this->request->getPost('required'), FILTER_VALIDATE_BOOLEAN),
        ]);
        $questionId = (int) $this->questionModel->getInsertID();

        foreach ($type === 'multiple_choice' ? $options : [] as $option) {
            $this->choiceModel->insert([
                'question_id' => $questionId,
                'choice_text' => $option,
            ]);
        }

        $this->db->transComplete();

        if (! $this->db->transStatus()) {
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'message' => 'Unable to save question right now.',
            ]);
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Question added successfully.',
            'data' => $this->getQuestionById($questionId),
        ]);
    }

    public function updateQuestion(int $questionId): ResponseInterface
    {
        if (! $this->isStaffAuthorized()) {
            return $this->response->setStatusCode(403)->setJSON([
                'success' => false,
                'message' => 'Forbidden.',
            ]);
        }

        $question = $this->questionModel->find($questionId);
        if (! is_array($question)) {
            return $this->response->setStatusCode(404)->setJSON([
                'success' => false,
                'message' => 'Question not found.',
            ]);
        }

        $category = $this->categoryModel->find((int) $question['category_id']);
        if (! is_array($category)) {
            return $this->response->setStatusCode(404)->setJSON([
                'success' => false,
                'message' => 'Question category not found.',
            ]);
        }

        $raw = $this->getRequestData();
        $text = trim((string) ($raw['text'] ?? ''));
        $type = trim((string) ($raw['type'] ?? (string) ($question['question_type'] ?? '')));
        $options = $this->normalizeQuestionOptions($raw['options'] ?? []);
        $categoryId = $this->resolveCategoryForSurvey(
            (int) $category['survey_id'],
            isset($raw['category_id']) ? (int) $raw['category_id'] : (int) $question['category_id'],
            trim((string) ($raw['category_name'] ?? ''))
        );

        if ($text === '') {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'Question text is required.',
            ]);
        }

        if (! in_array($type, ['text', 'multiple_choice', 'rating', 'image'], true)) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'Invalid question type.',
            ]);
        }

        if ($type === 'multiple_choice' && count($options) < 2) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'Multiple choice questions need at least 2 options.',
            ]);
        }

        $this->db->transStart();

        $this->questionModel->update($questionId, [
            'category_id' => $categoryId,
            'question_text' => $text,
            'question_type' => $type,
            'required' => filter_var($this->request->getPost('required'), FILTER_VALIDATE_BOOLEAN),
        ]);

        $this->choiceModel->where('question_id', $questionId)->delete();
        foreach ($type === 'multiple_choice' ? $options : [] as $option) {
            $this->choiceModel->insert([
                'question_id' => $questionId,
                'choice_text' => $option,
            ]);
        }

        $this->db->transComplete();

        if (! $this->db->transStatus()) {
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'message' => 'Unable to update question right now.',
            ]);
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Question updated successfully.',
            'data' => $this->getQuestionById($questionId),
        ]);
    }

    public function deleteQuestion(int $questionId): ResponseInterface
    {
        if (! $this->isStaffAuthorized()) {
            return $this->response->setStatusCode(403)->setJSON([
                'success' => false,
                'message' => 'Forbidden.',
            ]);
        }

        $question = $this->questionModel->find($questionId);
        if (! is_array($question)) {
            return $this->response->setStatusCode(404)->setJSON([
                'success' => false,
                'message' => 'Question not found.',
            ]);
        }

        $normalizedText = strtolower(trim((string) (preg_replace('/\s+/', ' ', preg_replace('/[^a-z0-9\s]/i', '', (string) ($question['question_text'] ?? '')) ?? '') ?? '')));
        $preMadeQuestionTexts = [
            'what is your employment status',
            'how long did it take you to find your first job after graduation',
            'is your job related to your degree',
        ];
        if (in_array($normalizedText, $preMadeQuestionTexts, true)) {
            return $this->response->setStatusCode(403)->setJSON([
                'success' => false,
                'message' => 'Pre-made employment questions cannot be deleted.',
            ]);
        }

        $this->db->transStart();
        $this->db->table('answers')->where('question_id', $questionId)->delete();
        $this->choiceModel->where('question_id', $questionId)->delete();
        $this->questionModel->delete($questionId);
        $this->db->transComplete();

        if (! $this->db->transStatus()) {
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'message' => 'Unable to delete question right now.',
            ]);
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Question removed successfully.',
        ]);
    }

    public function submitResponse(int $surveyId): ResponseInterface
    {
        if (! session()->has('auth_user')) {
            return $this->response->setStatusCode(401)->setJSON([
                'success' => false,
                'message' => 'Unauthorized.',
            ]);
        }

        $survey = $this->surveyModel->find($surveyId);
        if (! is_array($survey)) {
            return $this->response->setStatusCode(404)->setJSON([
                'success' => false,
                'message' => 'Survey not found.',
            ]);
        }

        $raw = $this->getRequestData();
        $rawUserEmail = trim((string) ($raw['user_email'] ?? ''));

        $isStaffProxy = $this->isStaffAuthorized()
            && $rawUserEmail !== ''
            && filter_var($rawUserEmail, FILTER_VALIDATE_EMAIL);

        if (! $isStaffProxy && ! $this->isSurveyOpenForResponses($survey)) {
            return $this->response->setStatusCode(403)->setJSON([
                'success' => false,
                'message' => 'This survey is closed and no longer accepts alumni responses.',
            ]);
        }

        $questions = $this->getQuestionRecordsForSurvey($surveyId);
        if ($questions === []) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'This survey does not have any questions yet.',
            ]);
        }

        $answersPayload = $raw['answers'] ?? [];
        if (is_string($answersPayload)) {
            $decodedAnswers = json_decode($answersPayload, true);
            $answersPayload = is_array($decodedAnswers) ? $decodedAnswers : [];
        }
        if (! is_array($answersPayload)) {
            $answersPayload = [];
        }
        $answersByQuestion = [];
        foreach ($answersPayload as $item) {
            if (! is_array($item)) {
                continue;
            }

            $questionId = isset($item['question_id']) ? (int) $item['question_id'] : 0;
            if ($questionId > 0) {
                $answersByQuestion[$questionId] = $item;
            }
        }

        $questionMap = [];
        foreach ($questions as $question) {
            $questionMap[(int) $question['id']] = $question;
        }

        $normalizedAnswers = [];
        $hasAnyAnswered = false;

        foreach ($questionMap as $questionId => $question) {
            $answer = $answersByQuestion[$questionId] ?? null;
            $questionType = (string) $question['question_type'];

            if (! is_array($answer)) {
                $normalizedAnswers[] = [
                    'question_id' => $questionId,
                    'question_type' => $questionType,
                    'choice_id' => null,
                    'answer_text' => null,
                    'answer_image' => null,
                ];
                continue;
            }

            if ($questionType === 'multiple_choice') {
                $choiceId = isset($answer['choice_id']) ? (int) $answer['choice_id'] : 0;
                if ($choiceId <= 0) {
                    $normalizedAnswers[] = [
                        'question_id' => $questionId,
                        'question_type' => $questionType,
                        'choice_id' => null,
                        'answer_text' => null,
                        'answer_image' => null,
                    ];
                    continue;
                }

                $choice = $this->choiceModel
                    ->where('id', $choiceId)
                    ->where('question_id', $questionId)
                    ->first();

                if (! is_array($choice)) {
                    $normalizedAnswers[] = [
                        'question_id' => $questionId,
                        'question_type' => $questionType,
                        'choice_id' => null,
                        'answer_text' => null,
                        'answer_image' => null,
                    ];
                    continue;
                }

                $hasAnyAnswered = true;
                $normalizedAnswers[] = [
                    'question_id' => $questionId,
                    'question_type' => $questionType,
                    'choice_id' => $choiceId,
                    'answer_text' => null,
                    'answer_image' => null,
                ];
                continue;
            }

            if ($questionType === 'image') {
                $uploadedImage = $this->request->getFile('answer_image_' . $questionId);
                if (! ($uploadedImage instanceof UploadedFile) || ! $uploadedImage->isValid() || $uploadedImage->getError() === UPLOAD_ERR_NO_FILE) {
                    $normalizedAnswers[] = [
                        'question_id' => $questionId,
                        'question_type' => $questionType,
                        'choice_id' => null,
                        'answer_text' => null,
                        'answer_image' => null,
                    ];
                    continue;
                }

                $validationError = $this->validateSurveyAnswerImage($uploadedImage);
                if ($validationError !== null) {
                    return $this->response->setStatusCode(422)->setJSON([
                        'success' => false,
                        'message' => $validationError,
                    ]);
                }

                $hasAnyAnswered = true;
                $normalizedAnswers[] = [
                    'question_id' => $questionId,
                    'question_type' => $questionType,
                    'choice_id' => null,
                    'answer_text' => null,
                    'answer_image' => $uploadedImage,
                ];
                continue;
            }

            $answerText = trim((string) ($answer['answer_text'] ?? ''));
            if ($answerText === '') {
                $normalizedAnswers[] = [
                    'question_id' => $questionId,
                    'question_type' => $questionType,
                    'choice_id' => null,
                    'answer_text' => null,
                    'answer_image' => null,
                ];
                continue;
            }

            $hasAnyAnswered = true;
            $normalizedAnswers[] = [
                'question_id' => $questionId,
                'question_type' => $questionType,
                'choice_id' => null,
                'answer_text' => $answerText,
                'answer_image' => null,
            ];
        }

        if (! $hasAnyAnswered) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'Please answer at least one survey question before submitting.',
            ]);
        }

        $currentUser = session('auth_user');
        $respondentEmail = $isStaffProxy
            ? $this->normalizeSurveyEmail($rawUserEmail)
            : $this->resolveRespondentEmail(is_array($currentUser) ? $currentUser : null);
        $respondentName = $this->resolveRespondentName(
            is_array($currentUser) ? $currentUser : null,
            $respondentEmail,
            $raw['user_name'] ?? null
        );
        $respondentUserId = $this->resolveRespondentUserId(
            is_array($currentUser) ? $currentUser : null,
            $respondentEmail,
            $isStaffProxy
        );

        if (! $isStaffProxy && ! $this->surveyAllowsMultipleResponses($survey) && $respondentEmail !== null) {
            $existingResponse = $this->responseModel
                ->where('survey_id', $surveyId)
                ->where('user_email', $respondentEmail)
                ->first();

            if (is_array($existingResponse)) {
                return $this->response->setStatusCode(409)->setJSON([
                    'success' => false,
                    'message' => 'You already submitted this survey.',
                ]);
            }
        }

        $responseId = 0;
        $savedImagePaths = [];
        $this->db->transStart();

        $this->responseModel->insert([
            'survey_id' => $surveyId,
            'user_email' => $respondentEmail,
            'submitted_at' => date('Y-m-d H:i:s'),
            'survey_number' => 1,
            'user_name' => $respondentName,
            'user_id' => $respondentUserId,
        ]);
        $responseId = (int) $this->responseModel->getInsertID();

        foreach ($normalizedAnswers as $answer) {
            $answerImagePath = null;
            if (($answer['question_type'] ?? '') === 'image' && ($answer['answer_image'] ?? null) instanceof UploadedFile) {
                $answerImagePath = $this->storeSurveyAnswerImage(
                    $answer['answer_image'],
                    $surveyId,
                    (int) $answer['question_id'],
                    $respondentUserId ?: $respondentEmail
                );
                $savedImagePaths[] = $answerImagePath;
            }

            $this->answerModel->insert([
                'response_id' => $responseId,
                'question_id' => $answer['question_id'],
                'choice_id' => $answer['choice_id'],
                'answer_text' => $answer['answer_text'],
                'answer_image_path' => $answerImagePath,
            ]);
        }

        $this->db->transComplete();

        if (! $this->db->transStatus()) {
            foreach ($savedImagePaths as $savedImagePath) {
                $this->deleteSurveyAnswerImage($savedImagePath);
            }
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'message' => 'Unable to submit your survey response right now.',
            ]);
        }

        $saved = $this->responseModel->find($responseId);
        $surveyNumberOut = null;
        if (is_array($saved) && isset($saved['survey_number']) && $saved['survey_number'] !== null && $saved['survey_number'] !== '') {
            $surveyNumberOut = (int) $saved['survey_number'];
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Survey submitted successfully.',
            'data' => [
                'response_id' => $responseId,
                'survey_number' => $surveyNumberOut,
            ],
        ]);
    }

    public function listResponses(int $surveyId): ResponseInterface
    {
        if (! session()->has('auth_user')) {
            return $this->response->setStatusCode(401)->setJSON([
                'success' => false,
                'message' => 'Unauthorized.',
            ]);
        }

        $survey = $this->surveyModel->find($surveyId);
        if (! is_array($survey)) {
            return $this->response->setStatusCode(404)->setJSON([
                'success' => false,
                'message' => 'Survey not found.',
            ]);
        }

        return $this->response->setJSON([
            'success' => true,
            'survey' => $this->formatSurvey($survey, $this->getCategoriesForSurvey($surveyId)),
            'responses' => $this->getStructuredResponses($surveyId),
        ]);
    }

    public function getResponse(int $responseId): ResponseInterface
    {
        if (! session()->has('auth_user')) {
            return $this->response->setStatusCode(401)->setJSON([
                'success' => false,
                'message' => 'Unauthorized.',
            ]);
        }

        $response = $this->responseModel->find($responseId);
        if (! is_array($response)) {
            return $this->response->setStatusCode(404)->setJSON([
                'success' => false,
                'message' => 'Response not found.',
            ]);
        }

        $surveyId = (int) $response['survey_id'];
        $survey = $this->surveyModel->find($surveyId);

        $formattedResponse = $this->formatResponseRecord($response);
        if ($this->scopeResponsesForCurrentCoordinator([$formattedResponse]) === []) {
            return $this->response->setStatusCode(404)->setJSON([
                'success' => false,
                'message' => 'Response not found.',
            ]);
        }

        return $this->response->setJSON([
            'success' => true,
            'survey' => is_array($survey) ? $this->formatSurvey($survey, $this->getCategoriesForSurvey($surveyId)) : null,
            'response' => $formattedResponse,
        ]);
    }

    public function reviewResponse(int $responseId): ResponseInterface
    {
        if (! session()->has('auth_user')) {
            return $this->response->setStatusCode(401)->setJSON([
                'success' => false,
                'message' => 'Unauthorized.',
            ]);
        }

        $currentRole = strtolower(trim((string) (session('auth_user.role') ?? '')));
        if (! in_array($currentRole, ['coordinator', 'head_admin'], true)) {
            return $this->response->setStatusCode(403)->setJSON([
                'success' => false,
                'message' => 'Only coordinators may review responses.',
            ]);
        }

        $response = $this->responseModel->find($responseId);
        if (! is_array($response)) {
            return $this->response->setStatusCode(404)->setJSON([
                'success' => false,
                'message' => 'Response not found.',
            ]);
        }

        $formattedResponse = $this->formatResponseRecord($response);
        if ($this->scopeResponsesForCurrentCoordinator([$formattedResponse]) === []) {
            return $this->response->setStatusCode(404)->setJSON([
                'success' => false,
                'message' => 'Response not found.',
            ]);
        }

        $raw = $this->getRequestData();
        $reviewStatus = strtolower(trim((string) ($raw['review_status'] ?? '')));
        if (! in_array($reviewStatus, ['reviewed', 'needs_revision'], true)) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'review_status must be "reviewed" or "needs_revision".',
            ]);
        }

        $reviewComment = trim((string) ($raw['review_comment'] ?? ''));
        $reviewerEmail = trim((string) (session('auth_user.email') ?? ''));
        $staffRow = $reviewerEmail !== ''
            ? $this->db->table('staffs')->where('Email', $reviewerEmail)->get()->getRowArray()
            : null;

        if (is_array($staffRow) && isset($staffRow['Staff_ID'])) {
            $reviewerId = (int) $staffRow['Staff_ID'];
        } else {
            // Ensure a matching staff row exists to satisfy the foreign key constraint on response_reviews.reviewer_id
            $firstName = trim((string) (session('auth_user.first_name') ?? ''));
            $lastName = trim((string) (session('auth_user.last_name') ?? ''));
            if ($firstName === '' && $lastName === '') {
                $name = trim((string) (session('auth_user.name') ?? 'Coordinator User'));
                $parts = explode(' ', $name, 2);
                $firstName = $parts[0] ?? 'Coordinator';
                $lastName = $parts[1] ?? 'User';
            }
            $this->db->table('staffs')->insert([
                'Campus_ID'     => (int) (session('auth_user.campus') ?: 1),
                'First_Name'    => $firstName ?: 'Coordinator',
                'Last_Name'     => $lastName ?: 'User',
                'Email'         => $reviewerEmail ?: ('coordinator_' . (session('auth_user.id') ?? time()) . '@ustp.edu.ph'),
                'Position'      => 'Program Coordinator',
                'Role'          => 'Coordinator',
                'Status'        => 'Active',
            ]);
            $reviewerId = (int) $this->db->insertID();
        }

        $this->db->transStart();

        $this->db->table('responses')->where('id', $responseId)->update([
            'review_status' => $reviewStatus,
        ]);

        $this->db->table('response_reviews')->insert([
            'response_id'    => $responseId,
            'reviewer_id'    => $reviewerId,
            'review_status'  => $reviewStatus,
            'review_comment' => $reviewComment !== '' ? $reviewComment : null,
            'reviewed_at'    => date('Y-m-d H:i:s'),
        ]);

        $this->db->transComplete();

        if (! $this->db->transStatus()) {
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'message' => 'Unable to save review right now.',
            ]);
        }

        $updatedResponse = $this->responseModel->find($responseId);
        $formatted = is_array($updatedResponse) ? $this->formatResponseRecord($updatedResponse) : $formattedResponse;

        return $this->response->setJSON([
            'success' => true,
            'message' => $reviewStatus === 'reviewed'
                ? 'Response marked as reviewed.'
                : 'Response flagged as needing revision.',
            'response' => $formatted,
        ]);
    }

    public function answerImage(int $answerId): ResponseInterface
    {
        $currentRole = strtolower(trim((string) (session('auth_user.role') ?? '')));
        if (! session()->has('auth_user') || ! in_array($currentRole, ['head_admin', 'coordinator'], true)) {
            return $this->response->setStatusCode(401)->setBody('Unauthorized.');
        }

        $answer = $this->answerModel->find($answerId);
        if (! is_array($answer)) {
            return $this->response->setStatusCode(404)->setBody('Image not found.');
        }

        if (in_array($currentRole, ['coordinator', 'head_admin'], true)) {
            $responseRecord = $this->responseModel->find((int) ($answer['response_id'] ?? 0));
            $formattedResponse = is_array($responseRecord) ? $this->formatResponseRecord($responseRecord) : null;
            if ($formattedResponse === null || $this->scopeResponsesForCurrentCoordinator([$formattedResponse]) === []) {
                return $this->response->setStatusCode(403)->setBody('Forbidden.');
            }
        }

        $relativePath = trim((string) ($answer['answer_image_path'] ?? ''));
        if ($relativePath === '') {
            return $this->response->setStatusCode(404)->setBody('Image not found.');
        }

        if ($this->isSupabaseStoragePath($relativePath)) {
            return redirect()->to($this->buildSupabasePublicUrl($this->extractSupabaseObjectPath($relativePath)));
        }

        $absolutePath = $this->resolveSurveyAnswerImagePath($relativePath);
        if (! is_file($absolutePath)) {
            return $this->response->setStatusCode(404)->setBody('Image file not found.');
        }

        $file = new File($absolutePath);

        return $this->response
            ->setContentType($file->getMimeType() ?: 'application/octet-stream')
            ->setHeader('Content-Length', (string) filesize($absolutePath))
            ->setBody((string) file_get_contents($absolutePath));
    }

    public function statistics(int $surveyId): ResponseInterface
    {
        if (! session()->has('auth_user')) {
            return $this->response->setStatusCode(401)->setJSON([
                'success' => false,
                'message' => 'Unauthorized.',
            ]);
        }

        $survey = $this->surveyModel->find($surveyId);
        if (! is_array($survey)) {
            return $this->response->setStatusCode(404)->setJSON([
                'success' => false,
                'message' => 'Survey not found.',
            ]);
        }

        $questions = $this->getQuestionsForSurvey($surveyId);
        $responses = $this->getStructuredResponses($surveyId);
        $totalResponses = count($responses);

        $scopeService = new \App\Services\AssignmentScopeService();
        $scope = $scopeService->resolveCurrentUserScope();
        $activeAlumniBuilder = $this->alumniModel->builder()
            ->join('colleges c', 'c."College_ID" = alumni."College_ID"', 'left')
            ->join('departments d', 'd."Department_ID" = alumni."Department_ID"', 'left')
            ->where('LOWER(alumni."Status"::text)', 'active');
        if ($scope !== null) {
            $scopeService->applyAlumniScope($activeAlumniBuilder, $scope, 'alumni', 'c', 'd');
        }
        $activeAlumniCount = (int) $activeAlumniBuilder->countAllResults();
        $activeAlumniCount = max(1, $activeAlumniCount);

        $questionStats = [];
        foreach ($questions as $question) {
            $answers = [];
            foreach ($responses as $response) {
                foreach ($response['answers'] as $answer) {
                    if ((int) $answer['questionId'] === (int) $question['id']) {
                        $answers[] = $answer['value'];
                    }
                }
            }

            if (in_array($question['type'], ['text', 'image'], true)) {
                $summaryLabel = $question['type'] === 'image'
                    ? 'Image responses submitted'
                    : 'Text responses submitted';
                $textCount = count($answers);
                $questionStats[] = [
                    'questionId' => (int) $question['id'],
                    'questionText' => $question['text'],
                    'questionType' => $question['type'],
                    'categoryName' => (string) ($question['categoryName'] ?? 'Uncategorized'),
                    'textCount' => $textCount,
                    'totalAnswered' => $textCount,
                    'mean' => $totalResponses > 0 ? round(($textCount / $totalResponses) * 100, 2) : 0,
                    'answerRows' => [[
                        'answerText' => $summaryLabel,
                        'responseCount' => $textCount,
                        'mean' => $totalResponses > 0 ? round(($textCount / $totalResponses) * 100, 2) : 0,
                    ]],
                    'breakdown' => [],
                ];
                continue;
            }

            $breakdown = [];
            $options = is_array($question['options'] ?? null) ? $question['options'] : [];
            foreach ($options as $option) {
                $optionText = trim((string) $option);
                if ($optionText !== '') {
                    $breakdown[$optionText] = 0;
                }
            }

            foreach ($answers as $value) {
                $breakdown[$value] = ($breakdown[$value] ?? 0) + 1;
            }

            $totalAnswered = array_sum($breakdown);
            $answerRows = [];
            foreach ($breakdown as $answerText => $count) {
                $answerRows[] = [
                    'answerText' => $answerText,
                    'responseCount' => $count,
                    'mean' => $totalResponses > 0 ? round(($count / $totalResponses) * 100, 2) : 0,
                ];
            }

            $questionStats[] = [
                'questionId' => (int) $question['id'],
                'questionText' => $question['text'],
                'questionType' => $question['type'],
                'categoryName' => (string) ($question['categoryName'] ?? 'Uncategorized'),
                'textCount' => count($answers),
                'totalAnswered' => $totalAnswered,
                'mean' => $totalResponses > 0 ? round(($totalAnswered / $totalResponses) * 100, 2) : 0,
                'answerRows' => $answerRows,
                'breakdown' => $breakdown,
            ];
        }

        return $this->response->setJSON([
            'success' => true,
            'data' => [
                'totalResponses' => $totalResponses,
                'responseRate' => (int) round(($totalResponses / $activeAlumniCount) * 100),
                'questions' => $questionStats,
            ],
        ]);
    }

    public function batchStatistics(int $surveyId): ResponseInterface
    {
        if (! session()->has('auth_user')) {
            return $this->response->setStatusCode(401)->setJSON([
                'success' => false,
                'message' => 'Unauthorized.',
            ]);
        }

        $survey = $this->surveyModel->find($surveyId);
        if (! is_array($survey)) {
            return $this->response->setStatusCode(404)->setJSON([
                'success' => false,
                'message' => 'Survey not found.',
            ]);
        }

        // Build category-question list
        $categories = $this->getCategoriesForSurvey($surveyId);
        $allQuestions = [];
        foreach ($categories as $category) {
            foreach ($category['questions'] as $question) {
                $allQuestions[] = [
                    'questionId'   => (int) $question['id'],
                    'questionText' => (string) $question['text'],
                    'questionType' => (string) $question['type'],
                    'categoryName' => (string) $category['name'],
                    'options'      => $question['options'] ?? [],
                ];
            }
        }

        // Get responses with alumni graduation year
        $responseRows = $this->db->table('responses r')
            ->select('r.id, r.user_email, a.GraduationYear')
            ->join('alumni a', 'LOWER(a."Email") = LOWER(r.user_email)', 'left')
            ->where('r.survey_id', $surveyId)
            ->get()
            ->getResultArray();

        $responseIds = array_map(static fn (array $row): int => (int) $row['id'], $responseRows);

        $responsesByBatch = [];
        foreach ($responseRows as $row) {
            $batch = $row['GraduationYear'] ?? 'Unknown';
            $responsesByBatch[$batch][] = (int) $row['id'];
        }

        // Get all answers for these responses
        $answersByResponse = [];
        if ($responseIds !== []) {
            $answerRows = $this->db->table('answers ans')
                ->select('ans.response_id, ans.question_id, ans.choice_id, ans.answer_text, c.choice_text')
                ->join('choices c', 'c.id = ans.choice_id', 'left')
                ->whereIn('ans.response_id', $responseIds)
                ->get()
                ->getResultArray();

            foreach ($answerRows as $row) {
                $rid = (int) $row['response_id'];
                $answersByResponse[$rid][] = $row;
            }
        }

        // Total alumni per batch via SQL aggregation instead of loading all alumni rows
        $batchTotals = [];
        foreach ($this->alumniModel->getBatchSummaries() as $batchSummary) {
            $batch = (string) ($batchSummary['GraduationYear'] ?? 'Unknown');
            $batchTotals[$batch] = (int) ($batchSummary['alumni_count'] ?? 0);
        }

        // Get all registered batches from alumni_batches table (including those with 0 alumni)
        $registeredBatches = [];
        try {
            $batchRows = $this->db->table('alumni_batches')
                ->select('GraduationYear')
                ->orderBy('GraduationYear', 'DESC')
                ->get()
                ->getResultArray();
            foreach ($batchRows as $row) {
                $year = $row['GraduationYear'] ?? null;
                if ($year !== null) {
                    $registeredBatches[] = (string) $year;
                }
            }
        } catch (\Exception $e) {
            // Table might not exist, fall back to batchTotals keys
            $registeredBatches = array_keys($batchTotals);
        }

        // Build batch-level statistics
        $batchStats = [];
        $processedBatches = [];

        foreach ($responsesByBatch as $batch => $batchResponseIds) {
            $totalAlumni       = $batchTotals[$batch] ?? 0;
            $respondentCount   = count($batchResponseIds);
            $questionStats     = [];

            foreach ($allQuestions as $q) {
                $breakdown = [];
                foreach ($batchResponseIds as $rid) {
                    foreach ($answersByResponse[$rid] ?? [] as $answer) {
                        if ((int) $answer['question_id'] === $q['questionId']) {
                            $value = $answer['choice_text'] ?? $answer['answer_text'] ?? '';
                            $breakdown[$value] = ($breakdown[$value] ?? 0) + 1;
                        }
                    }
                }

                if (in_array($q['questionType'], ['text', 'image'], true)) {
                    $questionStats[] = [
                        'questionId'   => $q['questionId'],
                        'questionText' => $q['questionText'],
                        'questionType' => $q['questionType'],
                        'categoryName' => $q['categoryName'],
                        'textCount'    => array_sum($breakdown),
                        'breakdown'    => [],
                    ];
                } else {
                    $questionStats[] = [
                        'questionId'   => $q['questionId'],
                        'questionText' => $q['questionText'],
                        'questionType' => $q['questionType'],
                        'categoryName' => $q['categoryName'],
                        'textCount'    => array_sum($breakdown),
                        'breakdown'    => $breakdown,
                    ];
                }
            }

            $batchStats[] = [
                'batch'           => $batch,
                'totalAlumni'     => $totalAlumni,
                'respondentCount' => $respondentCount,
                'responseRate'    => $totalAlumni > 0 ? (int) round(($respondentCount / $totalAlumni) * 100) : 0,
                'questions'       => $questionStats,
            ];
            $processedBatches[] = (string) $batch;
        }

        // Add empty batches that have alumni but no responses
        foreach ($batchTotals as $batch => $totalAlumni) {
            if (! in_array((string) $batch, $processedBatches, true)) {
                $questionStats = [];
                foreach ($allQuestions as $q) {
                    $questionStats[] = [
                        'questionId'   => $q['questionId'],
                        'questionText' => $q['questionText'],
                        'questionType' => $q['questionType'],
                        'categoryName' => $q['categoryName'],
                        'textCount'    => 0,
                        'breakdown'    => [],
                    ];
                }

                $batchStats[] = [
                    'batch'           => $batch,
                    'totalAlumni'     => $totalAlumni,
                    'respondentCount' => 0,
                    'responseRate'    => 0,
                    'questions'       => $questionStats,
                ];
                $processedBatches[] = (string) $batch;
            }
        }

        // Add registered batches with 0 alumni that haven't been processed yet
        foreach ($registeredBatches as $batch) {
            if (! in_array($batch, $processedBatches, true)) {
                $questionStats = [];
                foreach ($allQuestions as $q) {
                    $questionStats[] = [
                        'questionId'   => $q['questionId'],
                        'questionText' => $q['questionText'],
                        'questionType' => $q['questionType'],
                        'categoryName' => $q['categoryName'],
                        'textCount'    => 0,
                        'breakdown'    => [],
                    ];
                }

                $batchStats[] = [
                    'batch'           => $batch,
                    'totalAlumni'     => 0,
                    'respondentCount' => 0,
                    'responseRate'    => 0,
                    'questions'       => $questionStats,
                ];
                $processedBatches[] = $batch;
            }
        }

        // Sort descending by batch year
        usort($batchStats, static fn (array $a, array $b): int => strcmp((string) $b['batch'], (string) $a['batch']));

        return $this->response->setJSON([
            'success' => true,
            'data'    => [
                'surveyId'    => $surveyId,
                'surveyTitle' => $survey['title'],
                'batches'     => $batchStats,
            ],
        ]);
    }

    private function isStaffAuthorized(): bool
    {
        return session()->has('auth_user') && strtolower((string) (session('auth_user.role') ?? '')) === 'head_admin';
    }

    /**
     * @return array<string, mixed>
     */
    private function getRequestData(): array
    {
        $contentType = strtolower($this->request->getHeaderLine('Content-Type'));

        if (str_contains($contentType, 'multipart/form-data') || str_contains($contentType, 'application/x-www-form-urlencoded')) {
            $raw = $this->request->getPost();
            return is_array($raw) ? $raw : [];
        }

        try {
            $raw = $this->request->getJSON(true);
            if (is_array($raw)) {
                return $raw;
            }
        } catch (\Throwable) {
            // Fall back to raw/post parsing when the payload is not JSON.
        }

        $raw = $this->request->getRawInput();
        if (is_array($raw) && $raw !== []) {
            return $raw;
        }

        $raw = $this->request->getPost();
        return is_array($raw) ? $raw : [];
    }

    private function normalizeSurveyEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    private function resolveRespondentEmail(?array $currentUser): ?string
    {
        if (! is_array($currentUser)) {
            return null;
        }

        $role = strtolower((string) ($currentUser['role'] ?? ''));
        if ($role !== 'alumni') {
            return null;
        }

        $email = trim((string) ($currentUser['email'] ?? ''));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return $this->normalizeSurveyEmail($email);
    }

    private function resolveRespondentName(?array $currentUser, ?string $respondentEmail, mixed $rawUserName = null): ?string
    {
        $providedName = trim((string) $rawUserName);
        if ($providedName !== '') {
            return $providedName;
        }

        if (is_array($currentUser)) {
            $name = trim((string) ($currentUser['name'] ?? ''));
            if ($name !== '') {
                return $name;
            }

            $firstName = trim((string) ($currentUser['first_name'] ?? ''));
            $lastName = trim((string) ($currentUser['last_name'] ?? ''));
            $fullName = trim($firstName . ' ' . $lastName);
            if ($fullName !== '') {
                return $fullName;
            }
        }

        if ($respondentEmail !== null) {
            $alumni = $this->findAlumniByEmail($respondentEmail);
            if (is_array($alumni)) {
                $fullName = $this->formatAlumniName($alumni);
                if ($fullName !== '') {
                    return $fullName;
                }
            }
        }

        return null;
    }

    private function resolveRespondentUserId(?array $currentUser, ?string $respondentEmail, bool $isStaffProxy): ?string
    {
        if (! $isStaffProxy && is_array($currentUser)) {
            $currentUserAccountId = trim((string) ($currentUser['account_id'] ?? ''));
            if ($currentUserAccountId !== '') {
                return $currentUserAccountId;
            }
        }

        if ($respondentEmail === null) {
            return null;
        }

        $userAccount = $this->userAccountModel->findByEmail($respondentEmail);
        if (! is_array($userAccount)) {
            return null;
        }

        $userAccountId = trim((string) ($userAccount['account_id'] ?? ''));

        return $userAccountId !== '' ? $userAccountId : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getStructuredSurveys(): array
    {
        $surveys = $this->surveyModel->orderBy('created_at', 'DESC')->findAll();
        if ($surveys === []) {
            return [];
        }

        $currentUser = session('auth_user');
        $alumniEmail = null;
        $isAlumni = is_array($currentUser) && strtolower((string) ($currentUser['role'] ?? '')) === 'alumni';
        if ($isAlumni) {
            $alumniEmail = $this->resolveRespondentEmail($currentUser);
            $surveys = array_values(array_filter($surveys, function (array $survey): bool {
                return $this->getSurveyState($survey) === 'posted';
            }));

            if ($surveys === []) {
                return [];
            }
        }

        $responseCountBySurveyId = [];
        if ($alumniEmail !== null) {
            $respRows = $this->responseModel->where('user_email', $alumniEmail)->findAll();
            foreach ($respRows as $row) {
                $sid = (int) $row['survey_id'];
                $responseCountBySurveyId[$sid] = ($responseCountBySurveyId[$sid] ?? 0) + 1;
            }
        }

        $surveyIds = array_map(static fn (array $survey): int => (int) $survey['id'], $surveys);
        $categoriesBySurvey = $this->getCategoriesForSurveyIds($surveyIds);

        return array_map(function (array $survey) use ($categoriesBySurvey, $responseCountBySurveyId, $alumniEmail): array {
            $surveyId = (int) $survey['id'];
            $formatted = $this->formatSurvey($survey, $categoriesBySurvey[$surveyId] ?? []);
            $cnt = $alumniEmail !== null ? ($responseCountBySurveyId[$surveyId] ?? 0) : 0;
            $respondedAlready = $alumniEmail !== null && $cnt > 0;

            $formatted['responseCount'] = $cnt;
            $formatted['alreadyResponded'] = $respondedAlready;

            return $formatted;
        }, $surveys);
    }

    /**
     * @param array<int> $surveyIds
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function getCategoriesForSurveyIds(array $surveyIds): array
    {
        $surveyIds = array_values(array_filter(array_map('intval', $surveyIds), static fn (int $id): bool => $id > 0));
        if ($surveyIds === []) {
            return [];
        }

        $categoryRows = $this->categoryModel
            ->whereIn('survey_id', $surveyIds)
            ->orderBy('order_number', 'ASC')
            ->orderBy('id', 'ASC')
            ->findAll();

        if ($categoryRows === []) {
            return [];
        }

        $categoryIds = array_map(static fn (array $category): int => (int) $category['id'], $categoryRows);
        $questionRows = $this->questionModel
            ->whereIn('category_id', $categoryIds)
            ->orderBy('id', 'ASC')
            ->findAll();

        $questionIds = array_map(static fn (array $question): int => (int) $question['id'], $questionRows);
        $choiceRows = $questionIds === []
            ? []
            : $this->choiceModel->whereIn('question_id', $questionIds)->orderBy('id', 'ASC')->findAll();

        $choicesByQuestion = [];
        foreach ($choiceRows as $choice) {
            $choicesByQuestion[(int) $choice['question_id']][] = [
                'id' => (int) $choice['id'],
                'text' => (string) $choice['choice_text'],
            ];
        }

        $logicRows = $questionIds === []
            ? []
            : $this->db->table('question_logic')->whereIn('question_id', $questionIds)->get()->getResultArray();

        $logicByQuestion = [];
        foreach ($logicRows as $logic) {
            $logicByQuestion[(int) $logic['question_id']][] = $logic;
        }

        $questionsByCategory = [];
        foreach ($questionRows as $question) {
            $questionId = (int) $question['id'];
            $choices = $choicesByQuestion[$questionId] ?? [];
            
            $choiceIdToOptionIndex = [];
            foreach ($choices as $index => $c) {
                $choiceIdToOptionIndex[(int) $c['id']] = $index;
            }

            $qLogicRules = [];
            $hasLogic = false;
            if (isset($logicByQuestion[$questionId])) {
                foreach ($logicByQuestion[$questionId] as $rule) {
                    $cId = (int) $rule['choice_id'];
                    if (isset($choiceIdToOptionIndex[$cId])) {
                        $qLogicRules[] = [
                            'optionIndex' => $choiceIdToOptionIndex[$cId],
                            'action' => (string) $rule['action'],
                            'targetCatId' => $rule['target_category_id'] !== null ? (int) $rule['target_category_id'] : null,
                        ];
                        $hasLogic = true;
                    }
                }
            }

            usort($qLogicRules, static fn ($a, $b) => $a['optionIndex'] <=> $b['optionIndex']);

            $questionsByCategory[(int) $question['category_id']][] = [
                'id' => $questionId,
                'text' => (string) $question['question_text'],
                'type' => (string) $question['question_type'],
                'required' => filter_var($question['required'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'categoryId' => (int) $question['category_id'],
                'options' => array_map(static fn (array $choice): string => $choice['text'], $choices),
                'choices' => $choices,
                'ratingMin' => isset($question['rating_min']) ? (int) $question['rating_min'] : null,
                'ratingMax' => isset($question['rating_max']) ? (int) $question['rating_max'] : null,
                'ratingMinLabel' => isset($question['rating_min_label']) ? (string) $question['rating_min_label'] : null,
                'ratingMaxLabel' => isset($question['rating_max_label']) ? (string) $question['rating_max_label'] : null,
                'logic' => [
                    'enabled' => $hasLogic,
                    'rules' => $qLogicRules,
                ],
            ];
        }

        $categoriesBySurvey = [];
        foreach ($categoryRows as $category) {
            $categoryId = (int) $category['id'];
            $categoriesBySurvey[(int) $category['survey_id']][] = [
                'id' => $categoryId,
                'name' => (string) $category['category_name'],
                'description' => (string) ($category['description'] ?? ''),
                'orderNumber' => isset($category['order_number']) ? (int) $category['order_number'] : 1,
                'questions' => $questionsByCategory[$categoryId] ?? [],
            ];
        }

        return $categoriesBySurvey;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getCategoriesForSurvey(int $surveyId): array
    {
        return $this->getCategoriesForSurveyIds([$surveyId])[$surveyId] ?? [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getQuestionsForSurvey(int $surveyId): array
    {
        $categories = $this->getCategoriesForSurvey($surveyId);
        $questions = [];
        foreach ($categories as $category) {
            foreach ($category['questions'] as $question) {
                $question['categoryName'] = (string) ($category['name'] ?? '');
                $questions[] = $question;
            }
        }

        return $questions;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getStructuredResponses(int $surveyId): array
    {
        $responses = $this->responseModel
            ->where('survey_id', $surveyId)
            ->orderBy('survey_number', 'DESC')
            ->orderBy('submitted_at', 'DESC')
            ->findAll();

        $formattedResponses = $this->formatResponseRecords($responses);

        return $this->scopeResponsesForCurrentCoordinator($formattedResponses);
    }

    /**
     * Limit survey responses to the logged-in Head Admin's campus or
     * Coordinator's Campus -> College -> Department assignment.
     * Developers and Alumni retain their existing visibility.
     *
     * @param array<int, array<string, mixed>> $responses
     * @return array<int, array<string, mixed>>
     */
    private function scopeResponsesForCurrentCoordinator(array $responses): array
    {
        $scopeService = new \App\Services\AssignmentScopeService();
        $scope = $scopeService->resolveCurrentUserScope();

        // Developers and Alumni are not restricted by Head Admin / Coordinator organizational scope
        if ($scope === null) {
            return $responses;
        }

        if (($scope['valid'] ?? false) !== true) {
            return [];
        }

        return array_values(array_filter($responses, static function (array $response) use ($scopeService, $scope): bool {
            $collegeId = (int) ($response['collegeId'] ?? 0);
            $departmentId = (int) ($response['departmentId'] ?? 0);

            if ($collegeId > 0 || $departmentId > 0) {
                return $scopeService->isAlumniInScope([
                    'College_ID'    => $collegeId,
                    'Department_ID' => $departmentId,
                ], $scope);
            }

            // Fallback name match only if IDs were not present on the formatted response
            if (($scope['role'] ?? '') === 'coordinator') {
                return strcasecmp(trim((string) ($response['college'] ?? '')), (string) ($scope['college_name'] ?? '')) === 0
                    && strcasecmp(trim((string) ($response['course'] ?? '')), (string) ($scope['department_name'] ?? '')) === 0;
            }

            return false;
        }));
    }

    /**
     * @param array<int, array<string, mixed>> $responses
     * @return array<int, array<string, mixed>>
     */
    private function formatResponseRecords(array $responses): array
    {
        if ($responses === []) {
            return [];
        }

        $responseIds = [];
        $normalizedEmails = [];
        foreach ($responses as $response) {
            $responseId = (int) ($response['id'] ?? 0);
            if ($responseId > 0) {
                $responseIds[] = $responseId;
            }

            $userEmailRaw = isset($response['user_email']) ? trim((string) $response['user_email']) : '';
            if ($userEmailRaw !== '') {
                $normalizedEmails[$this->normalizeSurveyEmail($userEmailRaw)] = true;
            }
        }

        $responseIds = array_values(array_unique($responseIds));
        $emailKeys = array_keys($normalizedEmails);

        // 1. Batch-load alumni records by email
        $alumniByEmail = [];
        if ($emailKeys !== []) {
            $alumniRows = $this->alumniModel->builder()
                ->select('alumni.id, alumni.alumni_id, alumni.FirstName, alumni.LastName, alumni.Email, alumni.GraduationYear, alumni.Status, alumni.College_ID, alumni.Department_ID, alumni.Province, alumni.Municipality, alumni.Barangay, alumni.Contact_Number, c.College_Name AS College, d.Department_Name AS Course')
                ->join('colleges c', 'c.College_ID = alumni.College_ID', 'left')
                ->join('departments d', 'd.Department_ID = alumni.Department_ID', 'left')
                ->whereIn('LOWER(alumni."Email")', $emailKeys)
                ->get()
                ->getResultArray();

            foreach ($alumniRows as $alumniRow) {
                $emailKey = $this->normalizeSurveyEmail((string) ($alumniRow['Email'] ?? ''));
                if ($emailKey !== '' && ! isset($alumniByEmail[$emailKey])) {
                    $alumniByEmail[$emailKey] = $alumniRow;
                }
            }
        }

        // 2. Batch-load answers for all responses
        $answersByResponseId = [];
        if ($responseIds !== []) {
            $answerRows = $this->db->table('answers a')
                ->select('a.id, a.response_id, a.question_id, a.choice_id, a.answer_text, a.answer_image_path, q.question_text, q.question_type, c.choice_text, cat.category_name')
                ->join('questions q', 'q.id = a.question_id', 'left')
                ->join('categories cat', 'cat.id = q.category_id', 'left')
                ->join('choices c', 'c.id = a.choice_id', 'left')
                ->whereIn('a.response_id', $responseIds)
                ->orderBy('a.id', 'ASC')
                ->get()
                ->getResultArray();

            foreach ($answerRows as $answerRow) {
                $answersByResponseId[(int) $answerRow['response_id']][] = $answerRow;
            }
        }

        // 3. Batch-load latest review for all responses
        $latestReviewByResponseId = [];
        if ($responseIds !== []) {
            $reviewRows = $this->db->table('response_reviews rr')
                ->select('rr.response_id, rr.review_status, rr.review_comment, rr.reviewed_at, s.First_Name, s.Last_Name, u.First_Name as user_first_name, u.Last_Name as user_last_name')
                ->join('staffs s', 's.Staff_ID = rr.reviewer_id', 'left')
                ->join('user_accounts u', 'u.Email = s.Email', 'left')
                ->whereIn('rr.response_id', $responseIds)
                ->orderBy('rr.reviewed_at', 'DESC')
                ->get()
                ->getResultArray();

            foreach ($reviewRows as $reviewRow) {
                $rid = (int) $reviewRow['response_id'];
                if (! isset($latestReviewByResponseId[$rid])) {
                    $latestReviewByResponseId[$rid] = $reviewRow;
                }
            }
        }

        return array_map(function (array $response) use ($alumniByEmail, $answersByResponseId, $latestReviewByResponseId): array {
            $responseId = (int) $response['id'];
            $userEmailRaw = isset($response['user_email']) ? trim((string) $response['user_email']) : '';
            $userEmail = $userEmailRaw !== '' ? $userEmailRaw : null;
            $respondentEmail = $userEmail !== null ? $this->normalizeSurveyEmail($userEmail) : null;
            $alumni = $respondentEmail !== null ? ($alumniByEmail[$respondentEmail] ?? null) : null;
            $alumniId = is_array($alumni) && isset($alumni['id']) ? (int) $alumni['id'] : null;

            $storedName = isset($response['user_name']) ? trim((string) $response['user_name']) : '';
            $resolvedName = $storedName !== ''
                ? $storedName
                : (is_array($alumni) ? $this->formatAlumniName($alumni) : '');
            if ($resolvedName === '') {
                $resolvedName = $respondentEmail ?? 'Alumni User';
            }

            $firstName = is_array($alumni) ? trim((string) ($alumni['FirstName'] ?? '')) : '';
            $lastName = is_array($alumni) ? trim((string) ($alumni['LastName'] ?? '')) : '';
            if ($firstName === '' && $lastName === '') {
                $nameParts = preg_split('/\s+/', trim($resolvedName)) ?: [];
                $firstName = (string) array_shift($nameParts);
                $lastName = implode(' ', $nameParts);
            }

            $answers = $answersByResponseId[$responseId] ?? [];

            $surveyNum = isset($response['survey_number']) && $response['survey_number'] !== null && $response['survey_number'] !== ''
                ? (int) $response['survey_number']
                : null;

            $reviewStatus = (string) ($response['review_status'] ?? 'pending');
            $latestReview = $latestReviewByResponseId[$responseId] ?? null;

            $latestReviewFormatted = null;
            if (is_array($latestReview)) {
                $reviewerName = trim(($latestReview['First_Name'] ?? $latestReview['user_first_name'] ?? '') . ' ' . ($latestReview['Last_Name'] ?? $latestReview['user_last_name'] ?? ''));
                $latestReviewFormatted = [
                    'reviewStatus'   => (string) ($latestReview['review_status'] ?? ''),
                    'reviewComment'  => (string) ($latestReview['review_comment'] ?? ''),
                    'reviewedAt'     => (string) ($latestReview['reviewed_at'] ?? ''),
                    'reviewerName'   => $reviewerName !== '' ? $reviewerName : 'Coordinator',
                ];
            }

            return [
                'id' => $responseId,
                'surveyId' => (int) $response['survey_id'],
                'surveyNumber' => $surveyNum,
                'alumniId' => $alumniId,
                'alumniRecordId' => is_array($alumni) ? (string) ($alumni['alumni_id'] ?? '') : '',
                'collegeId' => is_array($alumni) ? (int) ($alumni['College_ID'] ?? 0) : 0,
                'departmentId' => is_array($alumni) ? (int) ($alumni['Department_ID'] ?? 0) : 0,
                'college' => is_array($alumni) ? (string) ($alumni['College'] ?? '') : '',
                'course' => is_array($alumni) ? (string) ($alumni['Course'] ?? '') : '',
                'graduationYear' => is_array($alumni) ? (string) ($alumni['GraduationYear'] ?? '') : '',
                'respondentEmail' => $respondentEmail,
                'userName' => $resolvedName,
                'alumniName' => $resolvedName,
                'firstName' => $firstName,
                'lastName' => $lastName,
                'submittedAt' => (string) ($response['submitted_at'] ?? ''),
                'reviewStatus' => $reviewStatus,
                'latestReview' => $latestReviewFormatted,
                'answers' => array_map(function (array $answer): array {
                    $imagePath = trim((string) ($answer['answer_image_path'] ?? ''));
                    $value = $answer['choice_text'] ?? $answer['answer_text'] ?? '';
                    if ($imagePath !== '') {
                        $value = basename($imagePath);
                    }
                    return [
                        'id' => (int) $answer['id'],
                        'questionId' => (int) $answer['question_id'],
                        'questionText' => (string) ($answer['question_text'] ?? ''),
                        'questionType' => (string) ($answer['question_type'] ?? ''),
                        'categoryName' => (string) ($answer['category_name'] ?? ''),
                        'choiceId' => isset($answer['choice_id']) && $answer['choice_id'] !== null ? (int) $answer['choice_id'] : null,
                        'value' => (string) $value,
                        'imageUrl' => $imagePath !== '' ? $this->getSurveyAnswerImageUrl((int) $answer['id'], $imagePath) : null,
                    ];
                }, $answers),
            ];
        }, $responses);
    }

    /**
     * @param array<string, mixed> $response
     * @return array<string, mixed>
     */
    private function formatResponseRecord(array $response): array
    {
        return $this->formatResponseRecords([$response])[0];
    }

    /**
     * @param array<string, mixed> $survey
     * @param array<int, array<string, mixed>> $categories
     * @return array<string, mixed>
     */
    private function formatSurvey(array $survey, array $categories): array
    {
        $status = strtolower(trim((string) ($survey['status'] ?? 'open')));
        if (! in_array($status, ['open', 'closed'], true)) {
            $status = 'open';
        }

        $questions = [];
        foreach ($categories as $category) {
            foreach ($category['questions'] as $question) {
                $question['categoryName'] = (string) ($category['name'] ?? '');
                $questions[] = $question;
            }
        }

        return [
            'id' => (int) $survey['id'],
            'title' => (string) $survey['title'],
            'description' => (string) ($survey['description'] ?? ''),
            'createdAt' => (string) ($survey['created_at'] ?? ''),
            'status' => $status,
            'surveyState' => $this->getSurveyState($survey),
            'allowMultipleResponses' => $this->surveyAllowsMultipleResponses($survey),
            'currentBatch' => $this->getSurveyCurrentBatch($survey),
            'openDateTime' => $this->normalizeSurveyDatetime($survey['open_datetime'] ?? null),
            'closeDateTime' => $this->normalizeSurveyDatetime($survey['close_datetime'] ?? null),
            'isAcceptingResponses' => $this->isSurveyOpenForResponses($survey),
            'categories' => $categories,
            'questions' => $questions,
        ];
    }

    private function surveyAllowsMultipleResponses(array $survey): bool
    {
        return filter_var($survey['allow_multiple_responses'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    private function getSurveyState(array $survey): string
    {
        $state = strtolower(trim((string) ($survey['survey_state'] ?? 'not posted')));
        return in_array($state, ['posted', 'not posted'], true) ? $state : 'not posted';
    }

    private function getSurveyCurrentBatch(array $survey): int
    {
        return max(1, (int) ($survey['current_batch'] ?? 1));
    }

    private function normalizeSurveyDatetime(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        $timestamp = strtotime($text);
        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findAlumniByEmail(string $email): ?array
    {
        $normalized = $this->normalizeSurveyEmail($email);

        return $this->alumniModel->findByEmail($normalized)
            ?? $this->alumniModel->findByEmail($email);
    }

    /**
     * @param array<string, mixed> $alumni
     */
    private function formatAlumniName(array $alumni): string
    {
        return trim((string) (($alumni['FirstName'] ?? '') . ' ' . ($alumni['LastName'] ?? '')));
    }

    private function isSurveyOpenForResponses(array $survey): bool
    {
        $status = strtolower(trim((string) ($survey['status'] ?? 'open')));
        if (! ($status === '' || $status === 'open')) {
            return false;
        }

        $now = time();
        $openAt = $this->normalizeSurveyDatetime($survey['open_datetime'] ?? null);
        $closeAt = $this->normalizeSurveyDatetime($survey['close_datetime'] ?? null);

        if ($openAt !== null) {
            $openTimestamp = strtotime($openAt);
            if ($openTimestamp !== false && $now < $openTimestamp) {
                return false;
            }
        }

        if ($closeAt !== null) {
            $closeTimestamp = strtotime($closeAt);
            if ($closeTimestamp !== false && $now > $closeTimestamp) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getQuestionById(int $questionId): ?array
    {
        $question = $this->db->table('questions q')
            ->select('q.*, c.category_name, c.survey_id')
            ->join('categories c', 'c.id = q.category_id', 'left')
            ->where('q.id', $questionId)
            ->get()
            ->getRowArray();

        if (! is_array($question)) {
            return null;
        }

        $logicRows = $this->db->table('question_logic')->where('question_id', $questionId)->get()->getResultArray();
        $choiceIdToOptionIndex = [];
        foreach ($choices as $index => $c) {
            $choiceIdToOptionIndex[(int) $c['id']] = $index;
        }

        $qLogicRules = [];
        $hasLogic = false;
        foreach ($logicRows as $rule) {
            $cId = (int) $rule['choice_id'];
            if (isset($choiceIdToOptionIndex[$cId])) {
                $qLogicRules[] = [
                    'optionIndex' => $choiceIdToOptionIndex[$cId],
                    'action' => (string) $rule['action'],
                    'targetCatId' => $rule['target_category_id'] !== null ? (int) $rule['target_category_id'] : null,
                ];
                $hasLogic = true;
            }
        }
        usort($qLogicRules, static fn ($a, $b) => $a['optionIndex'] <=> $b['optionIndex']);

        return [
            'id' => (int) $question['id'],
            'text' => (string) $question['question_text'],
            'type' => (string) $question['question_type'],
            'categoryId' => (int) $question['category_id'],
            'categoryName' => (string) ($question['category_name'] ?? ''),
            'surveyId' => (int) ($question['survey_id'] ?? 0),
            'options' => array_map(static fn (array $choice): string => (string) $choice['choice_text'], $choices),
            'choices' => array_map(static fn (array $choice): array => [
                'id' => (int) $choice['id'],
                'text' => (string) $choice['choice_text'],
            ], $choices),
            'ratingMin' => isset($question['rating_min']) ? (int) $question['rating_min'] : null,
            'ratingMax' => isset($question['rating_max']) ? (int) $question['rating_max'] : null,
            'ratingMinLabel' => isset($question['rating_min_label']) ? (string) $question['rating_min_label'] : null,
            'ratingMaxLabel' => isset($question['rating_max_label']) ? (string) $question['rating_max_label'] : null,
            'logic' => [
                'enabled' => $hasLogic,
                'rules' => $qLogicRules,
            ],
        ];
    }

    /**
     * @param mixed $categories
     * @return array<int, array<string, mixed>>
     */
    private function normalizeCategoriesInput(mixed $categories): array
    {
        if (is_string($categories)) {
            $decoded = json_decode($categories, true);
            $categories = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($categories)) {
            return [];
        }

        $normalized = [];
        foreach ($categories as $category) {
            if (! is_array($category)) {
                continue;
            }

            $questions = [];
            $rawQuestions = $category['questions'] ?? [];
            if (is_string($rawQuestions)) {
                $decodedQuestions = json_decode($rawQuestions, true);
                $rawQuestions = is_array($decodedQuestions) ? $decodedQuestions : [];
            }

            if (is_array($rawQuestions)) {
                foreach ($rawQuestions as $question) {
                    if (! is_array($question)) {
                        continue;
                    }

                    $qData = [
                        'id' => isset($question['id']) ? (int) $question['id'] : null,
                        'text' => trim((string) ($question['text'] ?? $question['question_text'] ?? '')),
                        'type' => trim((string) ($question['type'] ?? $question['question_type'] ?? '')),
                        'required' => isset($question['required']) ? (bool) filter_var($question['required'], FILTER_VALIDATE_BOOLEAN) : false,
                        'options' => $this->normalizeQuestionOptions($question['options'] ?? []),
                    ];

                    if ($qData['type'] === 'rating') {
                        $qData['ratingMin'] = isset($question['ratingMin']) ? (int) $question['ratingMin'] : 1;
                        $qData['ratingMax'] = isset($question['ratingMax']) ? (int) $question['ratingMax'] : 5;
                        $qData['ratingMinLabel'] = isset($question['ratingMinLabel']) ? trim((string) $question['ratingMinLabel']) : '';
                        $qData['ratingMaxLabel'] = isset($question['ratingMaxLabel']) ? trim((string) $question['ratingMaxLabel']) : '';
                    }

                    if ($qData['type'] === 'multiple_choice' && isset($question['logic']) && is_array($question['logic'])) {
                        $qData['logic'] = [
                            'enabled' => filter_var($question['logic']['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
                            'rules' => is_array($question['logic']['rules'] ?? null) ? $question['logic']['rules'] : [],
                        ];
                    }

                    $questions[] = $qData;
                }
            }

            $normalized[] = [
                'id' => isset($category['id']) ? (int) $category['id'] : null,
                'name' => trim((string) ($category['name'] ?? $category['category_name'] ?? '')),
                'description' => trim((string) ($category['description'] ?? '')),
                'questions' => $questions,
            ];
        }

        return $normalized;
    }

    /**
     * @param mixed $options
     * @return array<int, string>
     */
    private function normalizeQuestionOptions(mixed $options): array
    {
        if (is_string($options)) {
            $decoded = json_decode($options, true);
            if (is_array($decoded)) {
                $options = $decoded;
            } else {
                $options = explode(',', $options);
            }
        }

        if (! is_array($options)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn ($value): string => trim((string) $value),
            $options
        ), static fn (string $value): bool => $value !== ''));
    }

    /**
     * @param array<int, array<string, mixed>> $categories
     * @return list<string>
     */
    private function validateSurveyWizardPayload(
        string $title,
        string $description,
        ?string $openDatetime,
        ?string $closeDatetime,
        array $categories,
        bool $allowPastDates = false
    ): array {
        $errors = [];

        if ($title === '') {
            $errors[] = 'Survey name is required';
        } elseif (strlen($title) > 200) {
            $errors[] = 'Survey name must be under 200 characters';
        }

        if (strlen($description) > 2000) {
            $errors[] = 'Description must be under 2000 characters';
        }

        $today = strtotime(date('Y-m-d'));
        $openDate = $openDatetime !== null ? strtotime(substr($openDatetime, 0, 10)) : false;
        $closeDate = $closeDatetime !== null ? strtotime(substr($closeDatetime, 0, 10)) : false;

        if ($openDatetime === null) {
            $errors[] = 'Opening date is required';
        } elseif (! $allowPastDates && $openDate !== false && $openDate < $today) {
            $errors[] = 'Opening date cannot be in the past';
        }

        if ($closeDatetime === null) {
            $errors[] = 'Closing date is required';
        } elseif (! $allowPastDates && $closeDate !== false && $closeDate < $today) {
            $errors[] = 'Closing date cannot be in the past';
        }

        if ($openDatetime !== null && $closeDatetime !== null && strtotime($closeDatetime) < strtotime($openDatetime)) {
            $errors[] = 'Closing date must be after opening date';
        }

        $errors = array_merge($errors, $this->validateCategoriesErrors($categories));

        return $errors;
    }

    /**
     * @param array<int, array<string, mixed>> $categories
     * @return list<string>
     */
    private function validateCategoriesErrors(array $categories): array
    {
        $errors = [];
        $totalQuestions = 0;

        if ($categories === []) {
            return ['At least one question is required'];
        }

        foreach ($categories as $categoryIndex => $category) {
            $categoryName = trim((string) ($category['name'] ?? ''));
            $displayName = $categoryName !== '' ? $categoryName : 'Category ' . ($categoryIndex + 1);

            if ($categoryName === '') {
                $errors[] = sprintf('Category %d: name is required', $categoryIndex + 1);
            }

            $questions = is_array($category['questions'] ?? null) ? $category['questions'] : [];
            foreach ($questions as $questionIndex => $question) {
                $totalQuestions++;
                $label = sprintf('Category "%s", Q%d', $displayName, $questionIndex + 1);
                $questionText = trim((string) ($question['text'] ?? ''));
                $questionType = trim((string) ($question['type'] ?? ''));
                $options = is_array($question['options'] ?? null) ? $question['options'] : [];

                if ($questionText === '') {
                    $errors[] = sprintf('%s: question text is required', $label);
                } elseif (strlen($questionText) > 500) {
                    $errors[] = sprintf('%s: question too long (max 500 chars)', $label);
                }

                if ($questionType === '') {
                    $errors[] = sprintf('%s: question type is required', $label);
                } elseif (! in_array($questionType, ['text', 'multiple_choice', 'rating', 'image'], true)) {
                    $errors[] = sprintf('%s: question type is invalid', $label);
                }

                if ($questionType === 'multiple_choice') {
                    if (count($options) < 2) {
                        $errors[] = sprintf('%s: multiple choice needs at least 2 options', $label);
                    }
                    foreach ($options as $optionIndex => $option) {
                        $optionText = trim((string) $option);
                        if ($optionText === '') {
                            $errors[] = sprintf('%s, Option %d: cannot be empty', $label, $optionIndex + 1);
                        } elseif (strlen($optionText) > 200) {
                            $errors[] = sprintf('%s, Option %d: too long (max 200 chars)', $label, $optionIndex + 1);
                        }
                    }
                }
            }
        }

        if ($totalQuestions === 0) {
            $errors[] = 'At least one question is required';
        }

        return $errors;
    }

    /**
     * @param array<int, array<string, mixed>> $categories
     */
    private function validateCategories(array $categories): ?string
    {
        $errors = $this->validateCategoriesErrors($categories);

        return $errors === [] ? null : $errors[0];
    }

    /**
     * @param array<int, array<string, mixed>> $categories
     */
    private function syncSurveyStructure(int $surveyId, array $categories): void
    {
        $existingCategories = $this->categoryModel->where('survey_id', $surveyId)->findAll();
        $existingCategoriesById = [];
        foreach ($existingCategories as $category) {
            $existingCategoriesById[(int) $category['id']] = $category;
        }

        $existingCategoryIds = array_keys($existingCategoriesById);
        $existingQuestions = $existingCategoryIds === []
            ? []
            : $this->questionModel->whereIn('category_id', $existingCategoryIds)->findAll();
        $existingQuestionsById = [];
        foreach ($existingQuestions as $question) {
            $existingQuestionsById[(int) $question['id']] = $question;
        }

        $keptCategoryIds = [];
        $keptQuestionIds = [];
        $categoryMap = [];
        $categoryMap = [];
        $categoryIndexMap = [];
        $categoryNameMap = [];

        // First pass: create/update categories and build the ID mapping map
        foreach (array_values($categories) as $categoryIndex => $category) {
            $incomingCategoryId = isset($category['id']) && $category['id'] !== null ? (string) $category['id'] : null;
            $catName = trim((string) ($category['name'] ?? ''));
            $categoryPayload = [
                'survey_id' => $surveyId,
                'category_name' => $catName !== '' ? $catName : 'Category ' . ($categoryIndex + 1),
                'description' => trim((string) ($category['description'] ?? '')),
                'order_number' => $categoryIndex + 1,
            ];

            if ($incomingCategoryId !== null && is_numeric($incomingCategoryId) && (int) $incomingCategoryId > 0 && isset($existingCategoriesById[(int) $incomingCategoryId])) {
                $this->categoryModel->update((int) $incomingCategoryId, $categoryPayload);
                $categoryId = (int) $incomingCategoryId;
            } else {
                $this->categoryModel->insert($categoryPayload);
                $categoryId = (int) $this->categoryModel->getInsertID();
            }

            $keptCategoryIds[] = $categoryId;
            if ($incomingCategoryId !== null && $incomingCategoryId !== '') {
                $categoryMap[$incomingCategoryId] = $categoryId;
            }
            $categoryIndexMap[$categoryIndex] = $categoryId;
            $categoryIndexMap[(string) $categoryIndex] = $categoryId;
            $categoryMap['cat_' . ($categoryIndex + 1)] = $categoryId;
            $categoryNameMap[strtolower($catName)] = $categoryId;
        }

        // Second pass: save questions and their dependencies (choices, logic, rating scales)
        foreach (array_values($categories) as $categoryIndex => $category) {
            $incomingCategoryId = isset($category['id']) && $category['id'] !== null ? (string) $category['id'] : null;
            $categoryId = $categoryIndexMap[$categoryIndex] ?? ($incomingCategoryId !== null ? ($categoryMap[$incomingCategoryId] ?? 0) : 0);
            if ($categoryId <= 0) {
                continue;
            }

            $questions = is_array($category['questions'] ?? null) ? $category['questions'] : [];
            foreach ($questions as $question) {
                $incomingQuestionId = isset($question['id']) && $question['id'] !== null ? (int) $question['id'] : 0;
                $questionPayload = [
                    'category_id' => $categoryId,
                    'question_text' => trim((string) ($question['text'] ?? '')),
                    'question_type' => trim((string) ($question['type'] ?? '')),
                    'required' => filter_var($question['required'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'rating_min' => isset($question['ratingMin']) ? (int) $question['ratingMin'] : (isset($question['rating_min']) ? (int) $question['rating_min'] : null),
                    'rating_max' => isset($question['ratingMax']) ? (int) $question['ratingMax'] : (isset($question['rating_max']) ? (int) $question['rating_max'] : null),
                    'rating_min_label' => isset($question['ratingMinLabel']) ? trim((string) $question['ratingMinLabel']) : null,
                    'rating_max_label' => isset($question['ratingMaxLabel']) ? trim((string) $question['ratingMaxLabel']) : null,
                ];

                if ($incomingQuestionId > 0 && isset($existingQuestionsById[$incomingQuestionId])) {
                    $this->questionModel->update($incomingQuestionId, $questionPayload);
                    $questionId = $incomingQuestionId;
                } else {
                    $this->questionModel->insert($questionPayload);
                    $questionId = (int) $this->questionModel->getInsertID();
                }

                $keptQuestionIds[] = $questionId;

                // Sync Choices (preserve existing choice IDs where possible so existing answers are not orphaned)
                $existingChoices = $this->choiceModel->where('question_id', $questionId)->orderBy('id', 'ASC')->findAll();
                $normalizedOptions = $questionPayload['question_type'] === 'multiple_choice'
                    ? $this->normalizeQuestionOptions($question['options'] ?? [])
                    : [];
                $insertedChoiceIds = [];
                foreach ($normalizedOptions as $optIndex => $option) {
                    if (isset($existingChoices[$optIndex])) {
                        $existingChoiceId = (int) $existingChoices[$optIndex]['id'];
                        if ((string) ($existingChoices[$optIndex]['choice_text'] ?? '') !== $option) {
                            $this->choiceModel->update($existingChoiceId, [
                                'choice_text' => $option,
                            ]);
                        }
                        $insertedChoiceIds[] = $existingChoiceId;
                    } else {
                        $this->choiceModel->insert([
                            'question_id' => $questionId,
                            'choice_text' => $option,
                        ]);
                        $insertedChoiceIds[] = (int) $this->choiceModel->getInsertID();
                    }
                }
                if (count($existingChoices) > count($normalizedOptions)) {
                    $extraChoiceIds = array_map(
                        static fn (array $c): int => (int) $c['id'],
                        array_slice($existingChoices, count($normalizedOptions))
                    );
                    if ($extraChoiceIds !== []) {
                        $this->choiceModel->whereIn('id', $extraChoiceIds)->delete();
                    }
                }

                // Sync Logic Rules
                $this->db->table('question_logic')->where('question_id', $questionId)->delete();
                if ($questionPayload['question_type'] === 'multiple_choice' 
                    && isset($question['logic']['enabled']) 
                    && $question['logic']['enabled'] === true
                    && is_array($question['logic']['rules'] ?? null)) {

                    foreach ($question['logic']['rules'] as $rule) {
                        $optIndex = isset($rule['optionIndex']) ? (int) $rule['optionIndex'] : -1;
                        if (isset($insertedChoiceIds[$optIndex])) {
                            $choiceId = $insertedChoiceIds[$optIndex];
                            $action = trim((string) ($rule['action'] ?? 'next_section'));
                            if (! in_array($action, ['next_section', 'go_to_section', 'submit'], true)) {
                                $action = 'next_section';
                            }
                            $targetCatId = null;
                            if ($action === 'go_to_section') {
                                $incomingTargetCatId = isset($rule['targetCatId']) && $rule['targetCatId'] !== null ? (string) $rule['targetCatId'] : '';
                                $targetCatIndex = isset($rule['targetCatIndex']) && is_numeric($rule['targetCatIndex']) ? (int) $rule['targetCatIndex'] : -1;

                                if ($incomingTargetCatId !== '' && isset($categoryMap[$incomingTargetCatId])) {
                                    $targetCatId = $categoryMap[$incomingTargetCatId];
                                } elseif ($targetCatIndex >= 0 && isset($categoryIndexMap[$targetCatIndex])) {
                                    $targetCatId = $categoryIndexMap[$targetCatIndex];
                                } elseif (is_numeric($incomingTargetCatId) && (int) $incomingTargetCatId > 0 && in_array((int) $incomingTargetCatId, $keptCategoryIds, true)) {
                                    $targetCatId = (int) $incomingTargetCatId;
                                } elseif (preg_match('/^cat_(\d+)$/', $incomingTargetCatId, $matches)) {
                                    $numericIdx = ((int) $matches[1]) - 1;
                                    if (isset($categoryIndexMap[$numericIdx])) {
                                        $targetCatId = $categoryIndexMap[$numericIdx];
                                    }
                                }
                            }

                            $this->db->table('question_logic')->insert([
                                'question_id' => $questionId,
                                'choice_id' => $choiceId,
                                'action' => $action,
                                'target_category_id' => $targetCatId,
                            ]);
                        }
                    }
                }
            }
        }

        $removedQuestionIds = array_values(array_diff(array_keys($existingQuestionsById), $keptQuestionIds));
        if ($removedQuestionIds !== []) {
            $this->deleteSurveyAnswerImagesForQuestionIds($removedQuestionIds);
            $this->db->table('answers')->whereIn('question_id', $removedQuestionIds)->delete();
            $this->choiceModel->whereIn('question_id', $removedQuestionIds)->delete();
            $this->db->table('question_logic')->whereIn('question_id', $removedQuestionIds)->delete();
            $this->questionModel->whereIn('id', $removedQuestionIds)->delete();
        }

        $removedCategoryIds = array_values(array_diff(array_keys($existingCategoriesById), $keptCategoryIds));
        if ($removedCategoryIds !== []) {
            $this->categoryModel->whereIn('id', $removedCategoryIds)->delete();
        }
    }

    private function deleteSurveyStructure(int $surveyId): void
    {
        $categories = $this->categoryModel->where('survey_id', $surveyId)->findAll();
        $categoryIds = array_map(static fn (array $category): int => (int) $category['id'], $categories);
        if ($categoryIds !== []) {
            $questions = $this->questionModel->whereIn('category_id', $categoryIds)->findAll();
            $questionIds = array_map(static fn (array $question): int => (int) $question['id'], $questions);
            if ($questionIds !== []) {
                $this->deleteSurveyAnswerImagesForQuestionIds($questionIds);
                $this->db->table('answers')->whereIn('question_id', $questionIds)->delete();
                $this->choiceModel->whereIn('question_id', $questionIds)->delete();
                $this->db->table('question_logic')->whereIn('question_id', $questionIds)->delete();
                $this->questionModel->whereIn('id', $questionIds)->delete();
            }
            $this->categoryModel->whereIn('id', $categoryIds)->delete();
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getQuestionRecordsForSurvey(int $surveyId): array
    {
        return $this->db->table('questions q')
            ->select('q.*')
            ->join('categories cat', 'cat.id = q.category_id')
            ->where('cat.survey_id', $surveyId)
            ->orderBy('cat.order_number', 'ASC')
            ->orderBy('q.id', 'ASC')
            ->get()
            ->getResultArray();
    }

    private function resolveCategoryForSurvey(int $surveyId, ?int $categoryId = null, string $categoryName = ''): int
    {
        if ($categoryId !== null && $categoryId > 0) {
            $category = $this->categoryModel
                ->where('id', $categoryId)
                ->where('survey_id', $surveyId)
                ->first();

            if (is_array($category)) {
                return (int) $category['id'];
            }
        }

        $categoryName = trim($categoryName);
        if ($categoryName !== '') {
            $existing = $this->categoryModel
                ->where('survey_id', $surveyId)
                ->where('category_name', $categoryName)
                ->first();

            if (is_array($existing)) {
                return (int) $existing['id'];
            }

            $nextOrder = ((int) $this->categoryModel->selectMax('order_number')->where('survey_id', $surveyId)->get()->getRow('order_number')) + 1;
            $this->categoryModel->insert([
                'survey_id' => $surveyId,
                'category_name' => $categoryName,
                'order_number' => max(1, $nextOrder),
            ]);

            return (int) $this->categoryModel->getInsertID();
        }

        $firstCategory = $this->categoryModel
            ->where('survey_id', $surveyId)
            ->orderBy('order_number', 'ASC')
            ->orderBy('id', 'ASC')
            ->first();

        if (is_array($firstCategory)) {
            return (int) $firstCategory['id'];
        }

        $this->categoryModel->insert([
            'survey_id' => $surveyId,
            'category_name' => 'General',
            'order_number' => 1,
        ]);

        return (int) $this->categoryModel->getInsertID();
    }

    private function validateSurveyAnswerImage(UploadedFile $file): ?string
    {
        if (! $file->isValid()) {
            return 'One of the uploaded images could not be processed.';
        }

        $mimeType = strtolower((string) $file->getMimeType());
        if (! in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) {
            return 'Survey image answers must be JPG, PNG, WEBP, or GIF files.';
        }

        if (($file->getSize() ?? 0) > 50 * 1024 * 1024) {
            return 'Survey image answers must be 50 MB or smaller.';
        }

        return null;
    }

    private function storeSurveyAnswerImage(UploadedFile $file, int $surveyId, int $questionId, ?string $respondentKey = null): string
    {
        $supabaseObjectPath = $this->uploadSurveyAnswerImageToSupabase($file, $surveyId, $questionId, $respondentKey);
        if ($supabaseObjectPath !== null) {
            return $this->buildSupabaseStorageReference($supabaseObjectPath);
        }

        $directory = $this->getSurveyAnswerUploadDirectory();
        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $extension = $file->getExtension() ?: $file->guessExtension() ?: 'bin';
        $safeRespondent = preg_replace('/[^a-zA-Z0-9_-]+/', '_', (string) ($respondentKey ?? 'guest'));
        $filename = sprintf(
            'survey_%d_question_%d_%s_%s.%s',
            $surveyId,
            $questionId,
            trim((string) $safeRespondent, '_') ?: 'guest',
            bin2hex(random_bytes(6)),
            strtolower($extension)
        );

        $file->move($directory, $filename, true);

        return 'survey_answers/' . $filename;
    }

    private function getSurveyAnswerUploadDirectory(): string
    {
        return rtrim(WRITEPATH, '\\/') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'survey_answers';
    }

    private function resolveSurveyAnswerImagePath(string $relativePath): string
    {
        return rtrim(WRITEPATH, '\\/') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($relativePath, '\\/'));
    }

    private function deleteSurveyAnswerImage(?string $relativePath): void
    {
        $path = trim((string) $relativePath);
        if ($path === '') {
            return;
        }

        if ($this->isSupabaseStoragePath($path)) {
            $this->deleteSupabaseObject($this->extractSupabaseObjectPath($path));
            return;
        }

        $absolutePath = $this->resolveSurveyAnswerImagePath($path);
        if (is_file($absolutePath)) {
            @unlink($absolutePath);
        }
    }

    /**
     * @param array<int, int> $questionIds
     */
    private function deleteSurveyAnswerImagesForQuestionIds(array $questionIds): void
    {
        if ($questionIds === []) {
            return;
        }

        $answers = $this->answerModel
            ->select('answer_image_path')
            ->whereIn('question_id', $questionIds)
            ->findAll();

        foreach ($answers as $answer) {
            $this->deleteSurveyAnswerImage($answer['answer_image_path'] ?? null);
        }
    }

    private function getSurveyAnswerImageUrl(int $answerId, ?string $knownImagePath = null): string
    {
        if ($knownImagePath !== null) {
            $path = trim($knownImagePath);
        } else {
            $answer = $this->answerModel->find($answerId);
            if (! is_array($answer)) {
                return base_url('surveys/answers/' . $answerId . '/image');
            }

            $path = trim((string) ($answer['answer_image_path'] ?? ''));
        }

        if ($path !== '' && $this->isSupabaseStoragePath($path)) {
            return $this->buildSupabasePublicUrl($this->extractSupabaseObjectPath($path));
        }

        return base_url('surveys/answers/' . $answerId . '/image');
    }

    private function uploadSurveyAnswerImageToSupabase(UploadedFile $file, int $surveyId, int $questionId, ?string $respondentKey = null): ?string
    {
        $projectUrl = $this->getSupabaseProjectUrl();
        $storage = new \App\Services\SupabaseStorageService();
        if (! $storage->isConfigured()) {
            return null;
        }

        $extension = strtolower($file->getExtension() ?: $file->guessExtension() ?: 'bin');
        $safeRespondent = preg_replace('/[^a-zA-Z0-9_-]+/', '_', (string) ($respondentKey ?? 'guest'));
        $filename = sprintf(
            'survey_%d_question_%d_%s_%s.%s',
            $surveyId,
            $questionId,
            trim((string) $safeRespondent, '_') ?: 'guest',
            bin2hex(random_bytes(6)),
            $extension
        );
        $objectPath = sprintf('survey_answers/%d/%d/%s', $surveyId, $questionId, $filename);
        $uploadedRef = $storage->uploadUploadedFile($file, $objectPath, false);

        if ($uploadedRef === null) {
            return null;
        }

        return $storage->extractObjectPath($uploadedRef);
    }

    private function deleteSupabaseObject(string $objectPath): void
    {
        $storage = new \App\Services\SupabaseStorageService();
        $storage->deleteReference($objectPath);
    }

    private function getSupabaseProjectUrl(): string
    {
        return trim((string) config('Supabase')->projectUrl);
    }

    private function getSupabaseSecretKey(): string
    {
        return trim((string) config('Supabase')->secretKey);
    }

    private function getSupabaseBucketName(): string
    {
        return trim((string) config('Supabase')->bucketName);
    }

    private function buildSupabaseStorageReference(string $objectPath): string
    {
        return 'supabase:' . ltrim($objectPath, '/');
    }

    private function isSupabaseStoragePath(string $path): bool
    {
        return str_starts_with($path, 'supabase:');
    }

    private function extractSupabaseObjectPath(string $path): string
    {
        return ltrim(substr($path, strlen('supabase:')), '/');
    }

    private function buildSupabasePublicUrl(string $objectPath): string
    {
        return rtrim($this->getSupabaseProjectUrl(), '/') . '/storage/v1/object/public/' . rawurlencode($this->getSupabaseBucketName()) . '/' . $this->encodeSupabaseObjectPath($objectPath);
    }

    private function encodeSupabaseObjectPath(string $path): string
    {
        return implode('/', array_map('rawurlencode', array_filter(explode('/', str_replace('\\', '/', $path)), static fn (string $segment): bool => $segment !== '')));
    }
}
