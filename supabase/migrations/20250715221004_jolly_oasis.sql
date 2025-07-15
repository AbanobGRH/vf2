/*
# VITA Health Platform Database Schema

1. New Tables
   - `users` - User profile information
   - `health_readings` - Health sensor data (heart rate, SpO2, glucose)
   - `locations` - GPS location tracking
   - `medications` - Medication information
   - `medication_reminders` - Scheduled medication reminders
   - `alerts` - System alerts and notifications
   - `family_members` - Family access management
   - `meal_times` - User meal schedule preferences

2. Security
   - Enable RLS on all tables
   - Add policies for authenticated users

3. Features
   - Real-time health monitoring
   - Location tracking with fall detection
   - Medication management with audio reminders
   - Family access control
   - AI analytics integration
*/

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    email text UNIQUE NOT NULL,
    full_name text NOT NULL,
    date_of_birth date,
    phone text,
    address text,
    blood_type text,
    medical_conditions text[],
    allergies text[],
    emergency_contact_name text,
    emergency_contact_phone text,
    primary_physician text,
    insurance_info text,
    breakfast_time time DEFAULT '08:00:00',
    lunch_time time DEFAULT '12:00:00',
    dinner_time time DEFAULT '18:00:00',
    snack_times time[] DEFAULT ARRAY['10:00:00', '15:00:00']::time[],
    dietary_restrictions text[],
    preferred_meal_size text DEFAULT 'medium',
    created_at timestamptz DEFAULT now(),
    updated_at timestamptz DEFAULT now()
);

-- Health readings table (removed blood pressure)
CREATE TABLE IF NOT EXISTS health_readings (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id uuid REFERENCES users(id) ON DELETE CASCADE,
    heart_rate integer,
    spo2 integer,
    glucose_level integer,
    reading_timestamp timestamptz DEFAULT now(),
    device_id text,
    created_at timestamptz DEFAULT now()
);

-- Locations table
CREATE TABLE IF NOT EXISTS locations (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id uuid REFERENCES users(id) ON DELETE CASCADE,
    latitude decimal(10, 8),
    longitude decimal(11, 8),
    accuracy decimal(5, 2),
    speed decimal(5, 2),
    location_timestamp timestamptz DEFAULT now(),
    is_safe_zone boolean DEFAULT false,
    zone_name text,
    created_at timestamptz DEFAULT now()
);

-- Medications table
CREATE TABLE IF NOT EXISTS medications (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id uuid REFERENCES users(id) ON DELETE CASCADE,
    name text NOT NULL,
    dosage text NOT NULL,
    frequency text NOT NULL,
    times time[] NOT NULL,
    condition_for text,
    instructions text,
    is_active boolean DEFAULT true,
    created_at timestamptz DEFAULT now(),
    updated_at timestamptz DEFAULT now()
);

-- Medication reminders table
CREATE TABLE IF NOT EXISTS medication_reminders (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id uuid REFERENCES users(id) ON DELETE CASCADE,
    medication_id uuid REFERENCES medications(id) ON DELETE CASCADE,
    reminder_time timestamptz NOT NULL,
    is_taken boolean DEFAULT false,
    audio_file_path text,
    created_at timestamptz DEFAULT now(),
    taken_at timestamptz
);

-- Alerts table
CREATE TABLE IF NOT EXISTS alerts (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id uuid REFERENCES users(id) ON DELETE CASCADE,
    alert_type text NOT NULL,
    message text NOT NULL,
    severity text DEFAULT 'medium',
    is_read boolean DEFAULT false,
    metadata jsonb,
    created_at timestamptz DEFAULT now(),
    read_at timestamptz
);

-- Family members table
CREATE TABLE IF NOT EXISTS family_members (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id uuid REFERENCES users(id) ON DELETE CASCADE,
    name text NOT NULL,
    email text NOT NULL,
    relationship text NOT NULL,
    access_level text DEFAULT 'basic',
    permissions text[] DEFAULT ARRAY['health_data', 'alerts']::text[],
    is_active boolean DEFAULT true,
    created_at timestamptz DEFAULT now()
);

-- Safe zones table
CREATE TABLE IF NOT EXISTS safe_zones (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id uuid REFERENCES users(id) ON DELETE CASCADE,
    name text NOT NULL,
    latitude decimal(10, 8) NOT NULL,
    longitude decimal(11, 8) NOT NULL,
    radius integer DEFAULT 50,
    is_active boolean DEFAULT true,
    created_at timestamptz DEFAULT now()
);

-- Fall detection events
CREATE TABLE IF NOT EXISTS fall_events (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id uuid REFERENCES users(id) ON DELETE CASCADE,
    latitude decimal(10, 8),
    longitude decimal(11, 8),
    acceleration_data jsonb,
    confidence_level decimal(3, 2),
    is_confirmed boolean DEFAULT false,
    created_at timestamptz DEFAULT now(),
    confirmed_at timestamptz
);

-- Enable RLS on all tables
ALTER TABLE users ENABLE ROW LEVEL SECURITY;
ALTER TABLE health_readings ENABLE ROW LEVEL SECURITY;
ALTER TABLE locations ENABLE ROW LEVEL SECURITY;
ALTER TABLE medications ENABLE ROW LEVEL SECURITY;
ALTER TABLE medication_reminders ENABLE ROW LEVEL SECURITY;
ALTER TABLE alerts ENABLE ROW LEVEL SECURITY;
ALTER TABLE family_members ENABLE ROW LEVEL SECURITY;
ALTER TABLE safe_zones ENABLE ROW LEVEL SECURITY;
ALTER TABLE fall_events ENABLE ROW LEVEL SECURITY;

-- RLS Policies
CREATE POLICY "Users can read own data" ON users FOR SELECT TO authenticated USING (auth.uid() = id);
CREATE POLICY "Users can update own data" ON users FOR UPDATE TO authenticated USING (auth.uid() = id);

CREATE POLICY "Users can read own health readings" ON health_readings FOR SELECT TO authenticated USING (user_id = auth.uid());
CREATE POLICY "Users can insert own health readings" ON health_readings FOR INSERT TO authenticated WITH CHECK (user_id = auth.uid());

CREATE POLICY "Users can read own locations" ON locations FOR SELECT TO authenticated USING (user_id = auth.uid());
CREATE POLICY "Users can insert own locations" ON locations FOR INSERT TO authenticated WITH CHECK (user_id = auth.uid());

CREATE POLICY "Users can manage own medications" ON medications FOR ALL TO authenticated USING (user_id = auth.uid());
CREATE POLICY "Users can manage own medication reminders" ON medication_reminders FOR ALL TO authenticated USING (user_id = auth.uid());
CREATE POLICY "Users can manage own alerts" ON alerts FOR ALL TO authenticated USING (user_id = auth.uid());
CREATE POLICY "Users can manage own family members" ON family_members FOR ALL TO authenticated USING (user_id = auth.uid());
CREATE POLICY "Users can manage own safe zones" ON safe_zones FOR ALL TO authenticated USING (user_id = auth.uid());
CREATE POLICY "Users can manage own fall events" ON fall_events FOR ALL TO authenticated USING (user_id = auth.uid());

-- Indexes for performance
CREATE INDEX IF NOT EXISTS idx_health_readings_user_timestamp ON health_readings(user_id, reading_timestamp DESC);
CREATE INDEX IF NOT EXISTS idx_locations_user_timestamp ON locations(user_id, location_timestamp DESC);
CREATE INDEX IF NOT EXISTS idx_medication_reminders_time ON medication_reminders(reminder_time) WHERE NOT is_taken;
CREATE INDEX IF NOT EXISTS idx_alerts_user_created ON alerts(user_id, created_at DESC);

-- Insert sample data
INSERT INTO users (id, email, full_name, date_of_birth, phone, address, blood_type, medical_conditions, allergies, emergency_contact_name, emergency_contact_phone, primary_physician, breakfast_time, lunch_time, dinner_time, snack_times, dietary_restrictions, preferred_meal_size) VALUES 
('550e8400-e29b-41d4-a716-446655440000', 'margaret.thompson@email.com', 'Margaret Thompson', '1952-03-15', '(555) 123-4567', '123 Oak Street, Springfield, IL 62701', 'O+', ARRAY['Hypertension', 'Type 2 Diabetes', 'High Cholesterol'], ARRAY['Penicillin', 'Shellfish'], 'Sarah Thompson', '(555) 987-6543', 'Dr. Michael Chen', '07:30:00', '12:30:00', '18:30:00', ARRAY['10:00:00', '15:30:00']::time[], ARRAY['Low Sodium', 'Diabetic Friendly'], 'small')
ON CONFLICT (email) DO NOTHING;

-- Insert sample medications
INSERT INTO medications (user_id, name, dosage, frequency, times, condition_for, instructions) VALUES 
('550e8400-e29b-41d4-a716-446655440000', 'Metformin', '500mg', 'Twice daily', ARRAY['08:00:00', '20:00:00']::time[], 'Type 2 Diabetes', 'Take with food'),
('550e8400-e29b-41d4-a716-446655440000', 'Atorvastatin', '20mg', 'Once daily', ARRAY['20:00:00']::time[], 'High Cholesterol', 'Take in the evening')
ON CONFLICT DO NOTHING;