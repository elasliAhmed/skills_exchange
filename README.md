# Skills Exchange Platform

A full-stack web application where users can teach and learn skills from each other using a credit system.

## Tech Stack

- **Frontend**: HTML, CSS, Vanilla JavaScript
- **Backend**: PHP REST API with PDO
- **Database**: MySQL
- **Authentication**: JWT
- **Realtime/Video**: WebRTC + WebSocket signaling server

## Project Structure

```
/
├── frontend/
│   ├── index.html
│   ├── css/
│   │   ├── style.css
│   │   └── auth.css
│   └── js/
│       ├── api.js
│       ├── auth.js
│       ├── app.js
│       └── video.js
├── backend/
│   ├── api/
│   │   └── index.php
│   ├── config/
│   │   ├── database.php
│   │   └── jwt.php
│   ├── controllers/
│   │   ├── AuthController.php
│   │   ├── UserController.php
│   │   ├── SkillController.php
│   │   ├── LessonRequestController.php
│   │   ├── ChatController.php
│   │   └── ReviewController.php
│   └── models/
│       ├── User.php
│       ├── Skill.php
│       ├── UserSkill.php
│       ├── LessonRequest.php
│       ├── Transaction.php
│       ├── Message.php
│       └── Review.php
├── database/
│   └── schema.sql
└── signaling-server/
    └── server.js
```

## Setup Instructions

### 1. Database Setup

```bash
mysql -u root -p
CREATE DATABASE skills_exchange;
mysql -u root -p skills_exchange < database/schema.sql
```

### 2. Backend Configuration

Update database credentials in `backend/config/database.php`:

```php
private $host = 'localhost';
private $db_name = 'skills_exchange';
private $username = 'root';
private $password = '';
```

Update JWT secret key in `backend/config/jwt.php`:

```php
private static $secret_key = 'your-secret-key-change-this-in-production';
```

### 3. Signaling Server Setup

```bash
cd signaling-server
npm init -y
npm install ws
node server.js
```

### 4. Web Server Configuration

Configure Apache/Nginx to serve the frontend and backend. The API should be accessible at the URL configured in `frontend/js/api.js`.

### 5. Default Credits

New users start with 10 credits. Lesson requests cost 5 credits. Teaching a lesson rewards 5 credits.

## API Endpoints

### Auth
- POST `/api/register` - Register new user
- POST `/api/login` - Login user
- GET `/api/verify` - Verify JWT token

### Users
- GET `/api/profile` - Get current user profile
- PUT `/api/profile` - Update profile
- GET `/api/users/{id}` - Get public profile

### Skills
- GET `/api/skills` - Get all skills
- POST `/api/skills` - Add skill to user
- DELETE `/api/skills` - Remove user skill
- GET `/api/my-skills` - Get user skills
- GET `/api/search?skill=` - Search users by skill

### Lesson Requests
- POST `/api/requests` - Create lesson request
- GET `/api/requests?type=` - Get user requests
- PUT `/api/request/{id}` - Update request status
- POST `/api/request/{id}` - Complete request

### Chat
- POST `/api/messages` - Send message
- GET `/api/messages?user_id=` - Get conversation
- GET `/api/conversations` - Get all conversations

### Reviews
- POST `/api/reviews` - Create review
- GET `/api/reviews/{id}` - Get user reviews
- GET `/api/rating/{id}` - Get average rating

## Features

1. **User Authentication** - JWT-based register/login/logout
2. **User Profiles** - Bio, profile picture, skills
3. **Skill Management** - Add skills to teach or learn
4. **Skill Search** - Find teachers by skill
5. **Credit System** - Earn/spend credits for lessons
6. **Lesson Requests** - Send/accept/reject lesson requests
7. **Messaging** - Chat between users
8. **WebRTC Video** - Peer-to-peer video calls
9. **Reviews** - Rate and review after lessons