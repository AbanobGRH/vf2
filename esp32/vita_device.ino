#include <WiFi.h>
#include <WebServer.h>
#include <DNSServer.h>
#include <EEPROM.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <SoftwareSerial.h>
#include <Wire.h>
#include <MAX30100lib.h>
#include <MPU6050.h>
#include <DFRobotDFPlayerMini.h>
#include <FS.h>
#include <SPIFFS.h>

// WiFi Portal Configuration
WebServer server(80);
DNSServer dnsServer;
const byte DNS_PORT = 53;

// WiFi credentials storage
struct WiFiCredentials {
  char ssid[32];
  char password[64];
};

WiFiCredentials wifiNetworks[5]; // Store up to 5 networks
int networkCount = 0;

// Server configuration
const char* serverURL = "http://your-server.com/api/endpoint.php";
const char* audioURL = "http://your-server.com/api/medication_audio.php";
const char* apiKey = "vita_api_key_2024";
const char* userId = "550e8400-e29b-41d4-a716-446655440000";
const char* deviceId = "ESP32_VITA_001";

// Hardware pins (3.3V compatible)
#define GPS_RX_PIN 16
#define GPS_TX_PIN 17
#define MP3_RX_PIN 25
#define MP3_TX_PIN 26
#define SPEAKER_PIN 27
#define BATTERY_PIN A0
#define LED_PIN 2

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
  int glucoseLevel;
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
bool wifiConfigMode = false;

// Timing constants
const unsigned long HEALTH_INTERVAL = 30000;    // 30 seconds
const unsigned long LOCATION_INTERVAL = 60000;  // 1 minute
const unsigned long MEDICATION_CHECK_INTERVAL = 300000; // 5 minutes
const unsigned long DATA_SEND_INTERVAL = 60000;  // 1 minute

void setup() {
  Serial.begin(115200);
  pinMode(LED_PIN, OUTPUT);
  
  // Initialize EEPROM for WiFi storage
  EEPROM.begin(512);
  
  // Initialize SPIFFS for file storage
  if (!SPIFFS.begin(true)) {
    Serial.println("SPIFFS initialization failed");
  }
  
  // Load saved WiFi networks
  loadWiFiCredentials();
  
  // Try to connect to saved networks
  if (!connectToSavedNetworks()) {
    // Start WiFi configuration portal
    startWiFiPortal();
  } else {
    // Initialize sensors after WiFi connection
    initializeSensors();
  }
}

void loop() {
  if (wifiConfigMode) {
    dnsServer.processNextRequest();
    server.handleClient();
    return;
  }
  
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
  
  delay(1000);
}

void startWiFiPortal() {
  wifiConfigMode = true;
  
  // Create AP
  WiFi.mode(WIFI_AP);
  WiFi.softAP("VITA-Setup", "12345678");
  
  // Start DNS server
  dnsServer.start(DNS_PORT, "*", WiFi.softAPIP());
  
  // Setup web server routes
  server.on("/", handleRoot);
  server.on("/scan", handleScan);
  server.on("/save", handleSave);
  server.on("/reset", handleReset);
  server.onNotFound(handleRoot);
  
  server.begin();
  
  Serial.println("WiFi Portal Started");
  Serial.print("Connect to: VITA-Setup");
  Serial.print("Password: 12345678");
  Serial.print("Open: http://192.168.4.1");
  
  // Blink LED to indicate config mode
  for (int i = 0; i < 10; i++) {
    digitalWrite(LED_PIN, HIGH);
    delay(200);
    digitalWrite(LED_PIN, LOW);
    delay(200);
  }
}

void handleRoot() {
  String html = R"(
<!DOCTYPE html>
<html>
<head>
    <title>VITA WiFi Setup</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: Arial; margin: 20px; background: #f0f0f0; }
        .container { max-width: 400px; margin: 0 auto; background: white; padding: 20px; border-radius: 10px; }
        h1 { color: #4A90E2; text-align: center; }
        .network { margin: 10px 0; padding: 10px; border: 1px solid #ddd; border-radius: 5px; }
        input[type="text"], input[type="password"] { width: 100%; padding: 8px; margin: 5px 0; border: 1px solid #ddd; border-radius: 4px; }
        button { background: #4A90E2; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; width: 100%; margin: 5px 0; }
        button:hover { background: #3A7BC8; }
        .scan-btn { background: #7ED6A5; }
        .reset-btn { background: #E74C3C; }
        .status { padding: 10px; margin: 10px 0; border-radius: 5px; }
        .success { background: #d4edda; color: #155724; }
        .error { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🏥 VITA WiFi Setup</h1>
        <p>Configure WiFi networks for your VITA health device</p>
        
        <button class="scan-btn" onclick="scanNetworks()">🔍 Scan Networks</button>
        
        <div id="networks"></div>
        
        <h3>Add Network Manually</h3>
        <form id="wifiForm">
            <input type="text" id="ssid" placeholder="WiFi Network Name" required>
            <input type="password" id="password" placeholder="WiFi Password" required>
            <button type="submit">💾 Save Network</button>
        </form>
        
        <div id="savedNetworks">
            <h3>Saved Networks</h3>
            <div id="networkList"></div>
        </div>
        
        <button class="reset-btn" onclick="resetDevice()">🔄 Reset All Settings</button>
        
        <div id="status"></div>
    </div>

    <script>
        function scanNetworks() {
            document.getElementById('status').innerHTML = '<div class="status">Scanning networks...</div>';
            fetch('/scan')
                .then(response => response.json())
                .then(data => {
                    let html = '<h3>Available Networks</h3>';
                    data.networks.forEach(network => {
                        html += `<div class="network">
                            <strong>${network.ssid}</strong> (${network.rssi} dBm)
                            <button onclick="selectNetwork('${network.ssid}')" style="float: right;">Select</button>
                        </div>`;
                    });
                    document.getElementById('networks').innerHTML = html;
                    document.getElementById('status').innerHTML = '';
                });
        }
        
        function selectNetwork(ssid) {
            document.getElementById('ssid').value = ssid;
        }
        
        document.getElementById('wifiForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const ssid = document.getElementById('ssid').value;
            const password = document.getElementById('password').value;
            
            document.getElementById('status').innerHTML = '<div class="status">Saving network...</div>';
            
            fetch('/save', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `ssid=${encodeURIComponent(ssid)}&password=${encodeURIComponent(password)}`
            })
            .then(response => response.text())
            .then(data => {
                document.getElementById('status').innerHTML = `<div class="status success">${data}</div>`;
                document.getElementById('wifiForm').reset();
                loadSavedNetworks();
            })
            .catch(error => {
                document.getElementById('status').innerHTML = `<div class="status error">Error: ${error}</div>`;
            });
        });
        
        function loadSavedNetworks() {
            // This would load from device memory in a real implementation
            // For now, just show a placeholder
        }
        
        function resetDevice() {
            if (confirm('Reset all WiFi settings? Device will restart.')) {
                fetch('/reset').then(() => {
                    document.getElementById('status').innerHTML = '<div class="status">Device resetting...</div>';
                });
            }
        }
        
        // Load saved networks on page load
        loadSavedNetworks();
    </script>
</body>
</html>
  )";
  
  server.send(200, "text/html", html);
}

void handleScan() {
  int n = WiFi.scanNetworks();
  String json = "{\"networks\":[";
  
  for (int i = 0; i < n; i++) {
    if (i > 0) json += ",";
    json += "{\"ssid\":\"" + WiFi.SSID(i) + "\",\"rssi\":" + String(WiFi.RSSI(i)) + "}";
  }
  
  json += "]}";
  server.send(200, "application/json", json);
}

void handleSave() {
  String ssid = server.arg("ssid");
  String password = server.arg("password");
  
  if (ssid.length() > 0 && networkCount < 5) {
    // Save to memory
    ssid.toCharArray(wifiNetworks[networkCount].ssid, 32);
    password.toCharArray(wifiNetworks[networkCount].password, 64);
    networkCount++;
    
    // Save to EEPROM
    saveWiFiCredentials();
    
    // Try to connect
    WiFi.mode(WIFI_STA);
    WiFi.begin(ssid.c_str(), password.c_str());
    
    int attempts = 0;
    while (WiFi.status() != WL_CONNECTED && attempts < 20) {
      delay(500);
      attempts++;
    }
    
    if (WiFi.status() == WL_CONNECTED) {
      server.send(200, "text/plain", "Network saved and connected! Device will restart in normal mode.");
      delay(2000);
      wifiConfigMode = false;
      initializeSensors();
    } else {
      server.send(200, "text/plain", "Network saved but connection failed. Please check credentials.");
    }
  } else {
    server.send(400, "text/plain", "Invalid network data or storage full.");
  }
}

void handleReset() {
  // Clear EEPROM
  for (int i = 0; i < 512; i++) {
    EEPROM.write(i, 0);
  }
  EEPROM.commit();
  
  server.send(200, "text/plain", "Settings reset. Device restarting...");
  delay(1000);
  ESP.restart();
}

void saveWiFiCredentials() {
  EEPROM.write(0, networkCount);
  
  for (int i = 0; i < networkCount; i++) {
    int baseAddr = 1 + (i * 96); // 32 + 64 bytes per network
    
    for (int j = 0; j < 32; j++) {
      EEPROM.write(baseAddr + j, wifiNetworks[i].ssid[j]);
    }
    
    for (int j = 0; j < 64; j++) {
      EEPROM.write(baseAddr + 32 + j, wifiNetworks[i].password[j]);
    }
  }
  
  EEPROM.commit();
}

void loadWiFiCredentials() {
  networkCount = EEPROM.read(0);
  if (networkCount > 5) networkCount = 0; // Sanity check
  
  for (int i = 0; i < networkCount; i++) {
    int baseAddr = 1 + (i * 96);
    
    for (int j = 0; j < 32; j++) {
      wifiNetworks[i].ssid[j] = EEPROM.read(baseAddr + j);
    }
    
    for (int j = 0; j < 64; j++) {
      wifiNetworks[i].password[j] = EEPROM.read(baseAddr + 32 + j);
    }
  }
}

bool connectToSavedNetworks() {
  WiFi.mode(WIFI_STA);
  
  for (int i = 0; i < networkCount; i++) {
    Serial.printf("Trying to connect to: %s\n", wifiNetworks[i].ssid);
    WiFi.begin(wifiNetworks[i].ssid, wifiNetworks[i].password);
    
    int attempts = 0;
    while (WiFi.status() != WL_CONNECTED && attempts < 20) {
      delay(500);
      Serial.print(".");
      attempts++;
    }
    
    if (WiFi.status() == WL_CONNECTED) {
      Serial.println("\nWiFi connected!");
      Serial.print("IP address: ");
      Serial.println(WiFi.localIP());
      digitalWrite(LED_PIN, HIGH); // Solid LED = connected
      return true;
    }
  }
  
  return false;
}

void initializeSensors() {
  // Initialize sensors after WiFi connection
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
    mp3Player.volume(20);
  }
  
  // Initialize data structures
  memset(&currentHealth, 0, sizeof(currentHealth));
  memset(&currentLocation, 0, sizeof(currentLocation));
  memset(accelHistory, 0, sizeof(accelHistory));
  
  Serial.println("VITA Device initialized and connected");
}

void readHealthSensors(unsigned long currentTime) {
  if (currentTime - lastHealthReading >= HEALTH_INTERVAL) {
    heartSensor.update();
    
    if (heartSensor.getRawValues(&currentHealth.heartRate, &currentHealth.spo2)) {
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
  if (data.indexOf("$GPRMC") != -1) {
    int commaIndex[12];
    int commaCount = 0;
    
    for (int i = 0; i < data.length() && commaCount < 12; i++) {
      if (data.charAt(i) == ',') {
        commaIndex[commaCount++] = i;
      }
    }
    
    if (commaCount >= 9) {
      String latStr = data.substring(commaIndex[2] + 1, commaIndex[3]);
      String latDir = data.substring(commaIndex[3] + 1, commaIndex[4]);
      String lngStr = data.substring(commaIndex[4] + 1, commaIndex[5]);
      String lngDir = data.substring(commaIndex[5] + 1, commaIndex[6]);
      String speedStr = data.substring(commaIndex[6] + 1, commaIndex[7]);
      
      if (latStr.length() > 0 && lngStr.length() > 0) {
        currentLocation.latitude = convertDMSToDD(latStr, latDir);
        currentLocation.longitude = convertDMSToDD(lngStr, lngDir);
        currentLocation.speed = speedStr.toFloat() * 1.852;
        currentLocation.accuracy = 3.0;
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
    
    if (maxMagnitude > 20.0 && avgMagnitude < 8.0) {
      if (!fallDetected) {
        fallDetected = true;
        Serial.println("FALL DETECTED!");
        sendFallAlert();
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
    
    DynamicJsonDocument doc(1024);
    doc["user_id"] = userId;
    doc["device_id"] = deviceId;
    doc["timestamp"] = millis();
    
    if (currentHealth.timestamp > 0) {
      doc["heart_rate"] = currentHealth.heartRate;
      doc["spo2"] = currentHealth.spo2;
      if (currentHealth.glucoseLevel > 0) {
        doc["glucose_level"] = currentHealth.glucoseLevel;
      }
    }
    
    if (currentLocation.timestamp > 0) {
      doc["latitude"] = currentLocation.latitude;
      doc["longitude"] = currentLocation.longitude;
      doc["accuracy"] = currentLocation.accuracy;
      doc["speed"] = currentLocation.speed;
    }
    
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
  int trackNumber = filename.substring(filename.lastIndexOf('_') + 1, filename.lastIndexOf('.')).toInt();
  
  if (trackNumber > 0) {
    mp3Player.play(trackNumber);
    Serial.printf("Playing medication reminder: %s\n", filename.c_str());
    delay(10000);
  }
}