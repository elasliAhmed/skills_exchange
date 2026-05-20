// API Service
class API {
    constructor() {
        this.baseURL = 'http://localhost/skills_exchange/backend/api/';
        this._token = localStorage.getItem('token');
    }

    get token() {
        return localStorage.getItem('token');
    }

    setToken(token) {
        localStorage.setItem('token', token);
    }

    clearToken() {
        localStorage.removeItem('token');
    }

    async request(endpoint, method = 'GET', data = null) {
        const currentToken = localStorage.getItem('token');
        const url = this.baseURL + endpoint;
        console.log('[API]', method, url, '| Token present:', !!currentToken);
        const options = {
            method,
            headers: {
                'Content-Type': 'application/json',
                'Authorization': currentToken ? `Bearer ${currentToken}` : ''
            }
        };

        if (data && method !== 'GET') {
            options.body = JSON.stringify(data);
        }

        try {
            const response = await fetch(url, options);
            console.log('[API]', new Date().toISOString(), method, url, '| status:', response.status, response.statusText);
            const text = await response.text();
            console.log('[API] raw body:', text.substring(0, 600));
            let data;
            try {
                data = JSON.parse(text);
            } catch (jsonErr) {
                console.error('[API] not valid JSON. Full body:', text);
                data = { success: false, data: { error: 'Server returned non-JSON: ' + text.substring(0, 160) } };
            }
            if (!data || !data.success) console.warn('[API] error response:', JSON.stringify(data || {}));
            return data;
        } catch (error) {
            console.error('[API] request threw:', error);
            return { success: false, data: { error: error.message } };
        }
    }

    // Auth methods
    register(data) {
        return this.request('register.php', 'POST', data);
    }

    login(data) {
        return this.request('login.php', 'POST', data);
    }

    verifyToken() {
        return this.request('verify.php', 'GET');
    }

    // Skill methods — skills table removed; users name skills directly on forms
    getSkills() {
        console.debug('[API] getSkills() stubbed — skills catalogue removed');
        return this._stubSkillResponse();
    }

    searchSkill(skill) {
        console.debug('[API] searchSkill() stubbed — skills catalogue removed');
        return this._stubSkillResponse();
    }

    _stubSkillResponse() {
        return { success: true, data: { skills: [] } };
    }

    // ── Teacher Dashboard ──
    getTeacherStats() {
        return this.request('teacher.php?endpoint=stats', 'GET');
    }

    getTeacherEnrollments() {
        return this.request('teacher.php?endpoint=enrollments', 'GET');
    }

    getEnrollmentLessons(enrollment_id, student_name) {
        const q = `teacher.php?endpoint=lessons&enrollment_id=${enrollment_id}&student_name=${encodeURIComponent(student_name)}`;
        return this.request(q, 'GET');
    }

markLessonComplete(data) {
         return this.request('enrollments.php', 'POST', { action: 'complete_lesson', ...data });
     }

    // Lesson request methods
    createRequest(data) {
        return this.request('requests.php', 'POST', data);
    }

    getRequests(type = 'all') {
        return this.request(`requests.php?type=${type}`, 'GET');
    }

    // Teaching offers methods
    getTeachingOffers() {
        return this.request('teaching-offers.php', 'GET');
    }

    getMyTeachingOffers() {
        return this.request('teaching-offers.php?my=true', 'GET');
    }

    addTeachingOffer(data) {
        return this.request('teaching-offers.php', 'POST', data);
    }

    editTeachingOffer(data) {
        return this.request('teaching-offers.php', 'PUT', data);
    }

    enrollInOffer(offer_id) {
        return this.request('enrollments.php', 'POST', { offer_id });
    }

    getEnrollments(type = 'learner') {
        return this.request(`enrollments.php?type=${type}`, 'GET');
    }

    getMyEnrolledLessons() {
        return this.request('enrollments.php?type=learner', 'GET');
    }

    removeTeachingOffer(offer_id) {
        return this.request('teaching-offers.php', 'DELETE', { offer_id });
    }

    getOfferLearners(offer_id) {
        return this.request(`teaching-offers.php?learners=${offer_id}`, 'GET');
    }
}