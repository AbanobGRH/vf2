#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <SoftwareSerial.h>
#include <Wire.h>
#include <MAX30100lib.h>
#include <MPU6050.h>
#include <DFRobotDFPlayerMini.h>
#include <FS.h>
#include <SPIFFS.h>

// WiFi credentials
const char* ssid = "YOUR_WIFI_SSID";
const char* password = "YOUR_WIFI_PASSWORD";

// Server configuration
const char* serverURL = "http://your-server.com/api/endpoint.php";
const char* audioURL = "http://your-server.com/api/medication_audio.php";
const char* apiKey = "vita_api_key_2024";
const char* userId = "550e8400-e29b-41d4-a716-446655440000";
const char* deviceId = "ESP32_VITA_001";

// Hardware pins
#define GPS_RX_PIN 16
#define GPS_TX_PIN 17
#define MP3_RX_PIN 25
#define MP3_TX_PIN 26
#define SPEAKER_PIN 27
#define BATTERY_PIN A0

// Sensor objects
SoftwareSerial gpsSerial(GPS_RX_PIN, GPS_TX_PIN);
SoftwareSerial mp3Serial(MP3_RX_PIN, MP3_TX_PIN);
MAX30100 heartSensor;
MPU6050 mpu;
DFRobotDFPlayerMini mp3Player;

// Data structures
struct HealthData {
  int heartRate;
  int spo2;
  int glucoseLevel; // Would need additional sensor
  unsigned long timestamp;
};

struct LocationData {
  float latitude;
  float longitude;
  float accuracy;
  float speed;
  unsigned long timestamp;
};

struct AccelerationData {
  float x, y, z;
  float magnitude;
  unsigned long timestamp;
};

// Global variables
HealthData currentHealth;
LocationData currentLocation;
AccelerationData accelHistory[10];
int accelIndex = 0;
unsigned long lastHealthReading = 0;
unsigned long lastLocationReading = 0;
unsigned long lastMedicationCheck = 0;
unsigned long lastDataSend = 0;
bool fallDetected = false;

// Timing constants
const unsigned long HEALTH_INTERVAL = 30000;    // 30 seconds
const unsigned long LOCATION_INTERVAL = 60000;  // 1 minute
const unsigned long MEDICATION_CHECK_INTERVAL = 300000; // 5 minutes
const unsigned long DATA_SEND_INTERVAL = 60000;  // 1 minute

void setup() {
  Serial.begin(115200);
  gpsSerial.begin(9600);
  mp3Serial.begin(9600);
  
  // Initialize SPIFFS for file storage
  if (!SPIFFS.begin(true)) {
    Serial.println("SPIFFS initialization failed");
  }
  
  // Initialize sensors
  Wire.begin();
  
  if (!heartSensor.begin()) {
    Serial.println("MAX30100 initialization failed");
  }
  
  if (!mpu.begin()) {
    Serial.println("MPU6050 initialization failed");
  } else {
    mpu.setAccelerometerRange(MPU6050_RANGE_8_G);
    mpu.setGyroRange(MPU6050_RANGE_500_DEG);
    mpu.setFilterBandwidth(MPU6050_BAND_21_HZ);
  }
  
  // Initialize MP3 player
  if (!mp3Player.begin(mp3Serial)) {
    Serial.println("MP3 player initialization failed");
  } else {
    mp3Player.volume(20); // Set volume (0-30)
  }
  
  // Connect to WiFi
  WiFi.begin(ssid, password);
  while (WiFi.status() != WL_CONNECTED) {
    delay(1000);
    Serial.println("Connecting to WiFi...");
  }
  Serial.println("WiFi connected");
  Serial.print("IP address: ");
  Serial.println(WiFi.localIP());
  
  // Initialize data structures
  memset(&currentHealth, 0, sizeof(currentHealth));
  memset(&currentLocation, 0, sizeof(currentLocation));
  memset(accelHistory, 0, sizeof(accelHistory));
  
  Serial.println("VITA Device initialized");
}

void loop() {
  unsigned long currentTime = millis();
  
  // Read sensors
  readHealthSensors(currentTime);
  readLocationSensor(currentTime);
  readAccelerometer(currentTime);
  
  // Check for fall detection
  checkFallDetection();
  
  // Send data to server
  if (currentTime - lastDataSend >= DATA_SEND_INTERVAL) {
    sendDataToServer();
    lastDataSend = currentTime;
  }
  
  // Check for medication reminders
  if (currentTime - lastMedicationCheck >= MEDICATION_CHECK_INTERVAL) {
    checkMedicationReminders();
    lastMedicationCheck = currentTime;
  }
  
  delay(1000); // Main loop delay
}

void readHealthSensors(unsigned long currentTime) {
  if (currentTime - lastHealthReading >= HEALTH_INTERVAL) {
    heartSensor.update();
    
    if (heartSensor.getRawValues(&currentHealth.heartRate, &currentHealth.spo2)) {
      // Filter out invalid readings
      if (currentHealth.heartRate > 50 && currentHealth.heartRate < 200 &&
          currentHealth.spo2 > 80 && currentHealth.spo2 <= 100) {
        currentHealth.timestamp = currentTime;
        Serial.printf("Health: HR=%d, SpO2=%d\n", currentHealth.heartRate, currentHealth.spo2);
      }
    }
    
    lastHealthReading = currentTime;
  }
}

void readLocationSensor(unsigned long currentTime) {
  if (currentTime - lastLocationReading >= LOCATION_INTERVAL) {
    if (gpsSerial.available()) {
      String gpsData = gpsSerial.readString();
      if (parseGPSData(gpsData)) {
        currentLocation.timestamp = currentTime;
        Serial.printf("Location: Lat=%.6f, Lng=%.6f, Speed=%.2f\n", 
                     currentLocation.latitude, currentLocation.longitude, currentLocation.speed);
      }
    }
    lastLocationReading = currentTime;
  }
}

bool parseGPSData(String data) {
  // Simple NMEA parsing for GPRMC sentence
  if (data.indexOf("$GPRMC") != -1) {
    int commaIndex[12];
    int commaCount = 0;
    
    for (int i = 0; i < data.length() && commaCount < 12; i++) {
      if (data.charAt(i) == ',') {
        commaIndex[commaCount++] = i;
      }
    }
    
    if (commaCount >= 9) {
      // Extract latitude
      String latStr = data.substring(commaIndex[2] + 1, commaIndex[3]);
      String latDir = data.substring(commaIndex[3] + 1, commaIndex[4]);
      
      // Extract longitude
      String lngStr = data.substring(commaIndex[4] + 1, commaIndex[5]);
      String lngDir = data.substring(commaIndex[5] + 1, commaIndex[6]);
      
      // Extract speed
      String speedStr = data.substring(commaIndex[6] + 1, commaIndex[7]);
      
      if (latStr.length() > 0 && lngStr.length() > 0) {
        currentLocation.latitude = convertDMSToDD(latStr, latDir);
        currentLocation.longitude = convertDMSToDD(lngStr, lngDir);
        currentLocation.speed = speedStr.toFloat() * 1.852; // Convert knots to km/h
        currentLocation.accuracy = 3.0; // Assume 3m accuracy
        return true;
      }
    }
  }
  return false;
}

float convertDMSToDD(String dms, String direction) {
  if (dms.length() < 4) return 0.0;
  
  float degrees = dms.substring(0, 2).toFloat();
  float minutes = dms.substring(2).toFloat();
  
  float dd = degrees + (minutes / 60.0);
  
  if (direction == "S" || direction == "W") {
    dd = -dd;
  }
  
  return dd;
}

void readAccelerometer(unsigned long currentTime) {
  sensors_event_t a, g, temp;
  mpu.getEvent(&a, &g, &temp);
  
  AccelerationData& current = accelHistory[accelIndex];
  current.x = a.acceleration.x;
  current.y = a.acceleration.y;
  current.z = a.acceleration.z;
  current.magnitude = sqrt(current.x * current.x + current.y * current.y + current.z * current.z);
  current.timestamp = currentTime;
  
  accelIndex = (accelIndex + 1) % 10;
}

void checkFallDetection() {
  // Simple fall detection algorithm
  float totalMagnitude = 0;
  float maxMagnitude = 0;
  int validReadings = 0;
  
  for (int i = 0; i < 10; i++) {
    if (accelHistory[i].timestamp > 0) {
      totalMagnitude += accelHistory[i].magnitude;
      maxMagnitude = max(maxMagnitude, accelHistory[i].magnitude);
      validReadings++;
    }
  }
  
  if (validReadings >= 5) {
    float avgMagnitude = totalMagnitude / validReadings;
    
    // Fall detection criteria:
    // 1. Sudden spike in acceleration (impact)
    // 2. Followed by low acceleration (free fall or stillness)
    if (maxMagnitude > 20.0 && avgMagnitude < 8.0) {
      if (!fallDetected) {
        fallDetected = true;
        Serial.println("FALL DETECTED!");
        
        // Send immediate alert
        sendFallAlert();
        
        // Reset after 30 seconds
        delay(30000);
        fallDetected = false;
      }
    }
  }
}

void sendDataToServer() {
  if (WiFi.status() == WL_CONNECTED) {
    HTTPClient http;
    http.begin(serverURL);
    http.addHeader("Content-Type", "application/json");
    http.addHeader("X-API-Key", apiKey);
    
    // Create JSON payload
    DynamicJsonDocument doc(1024);
    doc["user_id"] = userId;
    doc["device_id"] = deviceId;
    doc["timestamp"] = millis();
    
    // Add health data if available
    if (currentHealth.timestamp > 0) {
      doc["heart_rate"] = currentHealth.heartRate;
      doc["spo2"] = currentHealth.spo2;
      if (currentHealth.glucoseLevel > 0) {
        doc["glucose_level"] = currentHealth.glucoseLevel;
      }
    }
    
    // Add location data if available
    if (currentLocation.timestamp > 0) {
      doc["latitude"] = currentLocation.latitude;
      doc["longitude"] = currentLocation.longitude;
      doc["accuracy"] = currentLocation.accuracy;
      doc["speed"] = currentLocation.speed;
    }
    
    // Add battery level
    int batteryLevel = map(analogRead(BATTERY_PIN), 0, 4095, 0, 100);
    doc["battery_level"] = batteryLevel;
    
    String jsonString;
    serializeJson(doc, jsonString);
    
    int httpResponseCode = http.POST(jsonString);
    
    if (httpResponseCode > 0) {
      String response = http.getString();
      Serial.printf("Server response: %d - %s\n", httpResponseCode, response.c_str());
    } else {
      Serial.printf("HTTP error: %d\n", httpResponseCode);
    }
    
    http.end();
  }
}

void sendFallAlert() {
  if (WiFi.status() == WL_CONNECTED) {
    HTTPClient http;
    http.begin(serverURL);
    http.addHeader("Content-Type", "application/json");
    http.addHeader("X-API-Key", apiKey);
    
    DynamicJsonDocument doc(1024);
    doc["user_id"] = userId;
    doc["device_id"] = deviceId;
    doc["fall_detected"] = true;
    doc["confidence_level"] = 0.85;
    doc["timestamp"] = millis();
    
    if (currentLocation.timestamp > 0) {
      doc["latitude"] = currentLocation.latitude;
      doc["longitude"] = currentLocation.longitude;
    }
    
    // Add acceleration data
    JsonArray accelData = doc.createNestedArray("acceleration_data");
    for (int i = 0; i < 10; i++) {
      if (accelHistory[i].timestamp > 0) {
        JsonObject accel = accelData.createNestedObject();
        accel["x"] = accelHistory[i].x;
        accel["y"] = accelHistory[i].y;
        accel["z"] = accelHistory[i].z;
        accel["magnitude"] = accelHistory[i].magnitude;
        accel["timestamp"] = accelHistory[i].timestamp;
      }
    }
    
    String jsonString;
    serializeJson(doc, jsonString);
    
    int httpResponseCode = http.POST(jsonString);
    Serial.printf("Fall alert sent: %d\n", httpResponseCode);
    
    http.end();
  }
}

void checkMedicationReminders() {
  if (WiFi.status() == WL_CONNECTED) {
    HTTPClient http;
    String url = String(audioURL) + "?action=get_files&user_id=" + userId + "&api_key=" + apiKey;
    
    http.begin(url);
    int httpResponseCode = http.GET();
    
    if (httpResponseCode == 200) {
      String response = http.getString();
      DynamicJsonDocument doc(2048);
      deserializeJson(doc, response);
      
      JsonArray newFiles = doc["new_files"];
      
      for (JsonVariant file : newFiles) {
        String filename = file["filename"];
        String url = file["url"];
        String reminderTime = file["reminder_time"];
        
        // Download and play audio file
        if (downloadAudioFile(url, filename)) {
          playMedicationReminder(filename);
        }
      }
    }
    
    http.end();
  }
}

bool downloadAudioFile(String url, String filename) {
  HTTPClient http;
  http.begin(url);
  
  int httpResponseCode = http.GET();
  
  if (httpResponseCode == 200) {
    WiFiClient* stream = http.getStreamPtr();
    
    File file = SPIFFS.open("/" + filename, FILE_WRITE);
    if (!file) {
      Serial.println("Failed to create file");
      return false;
    }
    
    uint8_t buffer[128];
    int len = 0;
    
    while (http.connected() && (len = stream->readBytes(buffer, sizeof(buffer))) > 0) {
      file.write(buffer, len);
    }
    
    file.close();
    Serial.printf("Downloaded: %s\n", filename.c_str());
    return true;
  }
  
  http.end();
  return false;
}

void playMedicationReminder(String filename) {
  // Convert filename to track number (simplified)
  int trackNumber = filename.substring(filename.lastIndexOf('_') + 1, filename.lastIndexOf('.')).toInt();
  
  if (trackNumber > 0) {
    mp3Player.play(trackNumber);
    Serial.printf("Playing medication reminder: %s\n", filename.c_str());
    
    // Wait for playback to complete (simplified)
    delay(10000);
  }
}