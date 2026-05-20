-- Migration: Add detail columns to user_skills table
ALTER TABLE user_skills
    ADD COLUMN skill_name VARCHAR(100) NOT NULL DEFAULT 'Custom Skill',
    ADD COLUMN skill_description TEXT,
    ADD COLUMN skill_level VARCHAR(50),
    ADD COLUMN lesson_format TEXT,
    ADD COLUMN learner_gains TEXT;
