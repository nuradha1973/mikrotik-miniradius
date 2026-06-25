<?php
/**
 * MINIRADIUS - MIKROTIK & FREERADIUS WEB PANEL
 * 
 * MikroTik RouterOS v7 Live Management Dashboard - Fully Configurable
 * Optimized for L009UIGS-2HaxD-IN running RouterOS v7.x (7.23) with SSL Support.
 * 
 * * Includes: 
 * - Hotspot & PPPoE User Management
 * - Real-time Network Traffic Monitoring (HTML5 Canvas)
 * 
 * @author Ken Dedes (GC Network Labs) - https://www.gcnetwork.my.id
 * @version 1.0.0
 */

session_start();

// Utility function to escape a string for safe use in JavaScript single-quoted strings within HTML attributes
if (!function_exists('miniradius_js_esc')) {
    function miniradius_js_esc($str) {
        return htmlspecialchars(addslashes((string)$str), ENT_QUOTES, 'UTF-8');
    }
}

// Utility function to format bytes into human-readable size
if (!function_exists('miniradius_format_bytes')) {
    function miniradius_format_bytes($bytes) {
        if ($bytes <= 0) return '0 B';
        $base = log($bytes, 1024);
        $suffixes = array('B', 'KB', 'MB', 'GB', 'TB');
        return round(pow(1024, $base - floor($base)), 2) . ' ' . $suffixes[floor($base)];
    }
}

// Utility function to format seconds into duration (e.g. 1h 23m 45s)
if (!function_exists('miniradius_format_duration')) {
    function miniradius_format_duration($seconds) {
        if ($seconds <= 0) return '0s';
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds / 60) % 60);
        $secs = $seconds % 60;
        
        $parts = [];
        if ($hours > 0) $parts[] = $hours . 'h';
        if ($minutes > 0) $parts[] = $minutes . 'm';
        if ($secs > 0 || empty($parts)) $parts[] = $secs . 's';
        
        return implode(' ', $parts);
    }
}

// 1. LOAD CONFIGURATION
$config_path = __DIR__ . '/config.php';
if (file_exists($config_path)) {
    $config = include $config_path;
} else {
    // Default values if config.php doesn't exist
    $config = [
        'db_host' => 'localhost',
        'db_user' => 'root',
        'db_pass' => 'kendedes123',
        'db_name' => 'radius_DB',
        'nas_secret' => 'radius123',
        'radius_log_path' => '/var/log/freeradius/radius.log',
        'mkt_ip' => '192.168.100.1',
        'mkt_user' => 'admin',
        'mkt_pass' => 'kendedes',
        'mkt_protocol' => 'http',
        'mkt_port' => '80',
        'mock_mode' => true,
    ];
}

// Helper function to query MikroTik RouterOS v7 REST API
if (!function_exists('miniradius_get_mikrotik_data')) {
    function miniradius_get_mikrotik_data($config, $path, $method = 'GET', $data = null) {
        $protocol = $config['mkt_protocol'] ?? 'http';
        $ip = $config['mkt_ip'] ?? '';
        $port = $config['mkt_port'] ?? '80';
        $user = $config['mkt_user'] ?? '';
        $pass = $config['mkt_pass'] ?? '';
        
        if (empty($ip) || empty($user)) {
            return null;
        }
        
        $url = "{$protocol}://{$ip}:{$port}/rest{$path}";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, "{$user}:{$pass}");
        curl_setopt($ch, CURLOPT_TIMEOUT, 2);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data !== null) {
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code >= 200 && $http_code < 300 && $response) {
            return json_decode($response, true);
        }
        
        return null;
    }
}

// 2. DATABASE CONNECTION (dilakukan sebelum API endpoint)
$db_connected = false;
$is_mock = $config['mock_mode'];
$conn = null;
$db_error = '';

if (!$is_mock) {
    try {
        $conn = new mysqli($config['db_host'], $config['db_user'], $config['db_pass'], $config['db_name']);
        if ($conn->connect_error) {
            $is_mock = true;
            $db_error = $conn->connect_error;
        } else {
            $db_connected = true;
        }
    } catch (Exception $e) {
        $is_mock = true;
        $db_error = $e->getMessage();
    }
}

// ------------------------------------------
// API ENDPOINT: REAL-TIME TRAFFIC STATISTICS
// ------------------------------------------
if (isset($_GET['action']) && $_GET['action'] === 'traffic') {
    header('Content-Type: application/json');
    
    $is_mock_now = $is_mock;
    
    $download = 0.0;
    $upload = 0.0;
    $cpu = 0;
    $temp = 0;
    $free_mem_mb = 0;
    $total_mem_mb = 0;
    $free_mem_pct = 0.0;
    $uptime_str = '';
    $success = false;
    
    if (!$is_mock_now) {
        // Query CPU Load & Memory
        $res_resource = miniradius_get_mikrotik_data($config, '/system/resource');
        if ($res_resource) {
            $cpu_val = isset($res_resource[0]) ? $res_resource[0] : $res_resource;
            $cpu = (int)($cpu_val['cpu-load'] ?? 0);
            
            // Format uptime
            $raw_uptime = $cpu_val['uptime'] ?? '0s';
            $uptime_str = preg_replace('/([dhms])/', '$1 ', $raw_uptime);
            $uptime_str = trim($uptime_str);
            
            // Memory stats
            $total_mem = (float)($cpu_val['total-memory'] ?? 512 * 1024 * 1024);
            $free_mem = (float)($cpu_val['free-memory'] ?? 382 * 1024 * 1024);
            $free_mem_mb = round($free_mem / (1024 * 1024), 0);
            $total_mem_mb = round($total_mem / (1024 * 1024), 0);
            $free_mem_pct = $total_mem > 0 ? round(($free_mem / $total_mem) * 100, 1) : 0;
            
            // Query CPU Temp
            $res_health = miniradius_get_mikrotik_data($config, '/system/health');
            if ($res_health && is_array($res_health)) {
                foreach ($res_health as $item) {
                    $name = strtolower($item['name'] ?? '');
                    if ($name === 'cpu-temperature' || $name === 'temperature' || $name === 'cpu_temperature') {
                        $temp = (int)($item['value'] ?? 0);
                        break;
                    }
                }
            }
            
            // Query Traffic
            $active_iface = $_SESSION['mkt_active_interface'] ?? 'ether1';
            
            $traffic = miniradius_get_mikrotik_data($config, '/interface/monitor-traffic', 'POST', [
                'interface' => $active_iface,
                'duration' => '1s'
            ]);
            
            // If ether1 or cached interface failed, list interfaces to find the best one
            if (!$traffic) {
                $interfaces = miniradius_get_mikrotik_data($config, '/interface');
                if ($interfaces && is_array($interfaces)) {
                    $found_iface = null;
                    // Look for ether1 or any interface containing 'wan' in name
                    foreach ($interfaces as $iface) {
                        if (isset($iface['name']) && ($iface['name'] === 'ether1' || strpos(strtolower($iface['name']), 'wan') !== false)) {
                            $found_iface = $iface['name'];
                            break;
                        }
                    }
                    
                    // Fallback to the first running ethernet interface
                    if (!$found_iface) {
                        foreach ($interfaces as $iface) {
                            if (($iface['running'] ?? false) === true && ($iface['type'] ?? '') === 'ether') {
                                $found_iface = $iface['name'];
                                break;
                            }
                        }
                    }
                    
                    if ($found_iface) {
                        $active_iface = $found_iface;
                        $_SESSION['mkt_active_interface'] = $active_iface; // cache it
                        
                        $traffic = miniradius_get_mikrotik_data($config, '/interface/monitor-traffic', 'POST', [
                            'interface' => $active_iface,
                            'duration' => '1s'
                        ]);
                    }
                }
            }
            
            if ($traffic) {
                $t_data = isset($traffic[0]) ? $traffic[0] : $traffic;
                $rx_bps = (float)($t_data['rx-bits-per-second'] ?? 0);
                $tx_bps = (float)($t_data['tx-bits-per-second'] ?? 0);
                
                $download = round($rx_bps / 1000000, 1);
                $upload = round($tx_bps / 1000000, 1);
            }
            
            $success = true;
        }
    }
    
    if (!$success) {
        // Graceful fallback to mock data
        if (!isset($_SESSION['last_dl'])) {
            $_SESSION['last_dl'] = 42.5;
            $_SESSION['last_ul'] = 11.2;
        }
        $_SESSION['last_dl'] += (rand(-35, 35) / 10.0);
        $_SESSION['last_ul'] += (rand(-10, 10) / 10.0);
        
        if ($_SESSION['last_dl'] < 5.0) $_SESSION['last_dl'] = rand(8, 20);
        if ($_SESSION['last_dl'] > 98.0) $_SESSION['last_dl'] = rand(70, 90);
        if ($_SESSION['last_ul'] < 1.0) $_SESSION['last_ul'] = rand(2, 6);
        if ($_SESSION['last_ul'] > 35.0) $_SESSION['last_ul'] = rand(20, 30);
        
        $download = round($_SESSION['last_dl'], 1);
        $upload = round($_SESSION['last_ul'], 1);
        $cpu = rand(5, 20);
        $temp = rand(41, 45);
        $free_mem_mb = 382;
        $total_mem_mb = 512;
        $free_mem_pct = 74.6;
        $uptime_str = '5d 12h 43m';
    }
    
    echo json_encode([
        'download' => $download,
        'upload' => $upload,
        'cpu' => $cpu,
        'temp' => $temp,
        'free_mem' => $free_mem_mb,
        'total_mem' => $total_mem_mb,
        'mem_pct' => $free_mem_pct,
        'uptime' => $uptime_str,
        'timestamp' => date('H:i:s')
    ]);
    exit;
}

// ------------------------------------------
// API ENDPOINT: TOGGLE MOCK MODE (AJAX)
// ------------------------------------------
if (isset($_GET['action']) && $_GET['action'] === 'toggle_mock' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $new_mock = ($_POST['mock_mode'] ?? '0') === '1';
    $config['mock_mode'] = $new_mock;
    $config_content = "<?php\n// MiniRadius Configuration File\nreturn " . var_export($config, true) . ";\n";
    if (file_put_contents($config_path, $config_content) !== false) {
        echo json_encode(['success' => true, 'message' => 'Demo Mode ' . ($new_mock ? 'diaktifkan' : 'dinonaktifkan')]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menulis file config']);
    }
    exit;
}

// Load mock session data if mock mode (refresh dari file setiap kali)
if ($is_mock) {
    $mock_session = include __DIR__ . '/data/mock_session_data.php';
    $_SESSION['mock_profiles'] = $mock_session['profiles'];
    $_SESSION['mock_users'] = $mock_session['users'];
    $_SESSION['mock_logs'] = $mock_session['logs'];
}

// Initialize alert variables
$alert_msg = $_SESSION['alert_msg'] ?? '';
$alert_type = $_SESSION['alert_type'] ?? 'success';
unset($_SESSION['alert_msg'], $_SESSION['alert_type']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // --- USER CRUD HANDLERS ---
    if ($action === 'add_user' || $action === 'edit_user') {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $profile = trim($_POST['profile'] ?? '');
        $old_username = trim($_POST['old_username'] ?? '');
        
        if (empty($username) || empty($password)) {
            $alert_msg = 'Username dan Password tidak boleh kosong!';
            $alert_type = 'error';
        } else {
            if ($is_mock) {
                if ($action === 'add_user') {
                    // Check if exists
                    $exists = false;
                    foreach ($_SESSION['mock_users'] as $u) {
                        if ($u['username'] === $username) { $exists = true; break; }
                    }
                    if ($exists) {
                        $alert_msg = 'User sudah terdaftar!';
                        $alert_type = 'error';
                    } else {
                        $_SESSION['mock_users'][] = ['username' => $username, 'password' => $password, 'profile' => $profile, 'status' => 'active'];
                        $alert_msg = 'User baru berhasil ditambahkan!';
                    }
                } else { // edit user
                    foreach ($_SESSION['mock_users'] as &$u) {
                        if ($u['username'] === $old_username) {
                            $u['username'] = $username;
                            $u['password'] = $password;
                            $u['profile'] = $profile;
                            break;
                        }
                    }
                    $alert_msg = 'User berhasil diperbarui!';
                }
            } else {
                // Real DB Write
                $conn->begin_transaction();
                try {
                    if ($action === 'add_user') {
                        // Check exists
                        $chk = $conn->prepare("SELECT id FROM radcheck WHERE username = ?");
                        $chk->bind_param("s", $username);
                        $chk->execute();
                        $chk->store_result();
                        if ($chk->num_rows > 0) {
                            throw new Exception('User sudah terdaftar di database!');
                        }
                        
                        // Insert password (hanya 1 baris, tanpa Mikrotik-Status)
                        $stmt1 = $conn->prepare("INSERT INTO radcheck (username, attribute, op, value) VALUES (?, 'Cleartext-Password', ':=', ?)");
                        $stmt1->bind_param("ss", $username, $password);
                        $stmt1->execute();
                        
                        // Insert user status as active by default ke tabel terpisah
                        $stmtStatus = $conn->prepare("INSERT INTO miniradius_user_status (username, status) VALUES (?, 'active') ON DUPLICATE KEY UPDATE status = 'active'");
                        if ($stmtStatus) {
                            $stmtStatus->bind_param("s", $username);
                            $stmtStatus->execute();
                            $stmtStatus->close();
                        }
                        
                        // Insert usergroup
                        if (!empty($profile)) {
                            $stmt2 = $conn->prepare("INSERT INTO radusergroup (username, groupname, priority) VALUES (?, ?, 1)");
                            $stmt2->bind_param("ss", $username, $profile);
                            $stmt2->execute();
                        }
                        $conn->commit();
                        $alert_msg = 'User baru berhasil disimpan ke database!';
                    } else { // edit user
                        // Delete old radcheck password
                        $stmt1 = $conn->prepare("DELETE FROM radcheck WHERE username = ? AND attribute = 'Cleartext-Password'");
                        $stmt1->bind_param("s", $old_username);
                        $stmt1->execute();
                        
                        // Insert new password
                        $stmt2 = $conn->prepare("INSERT INTO radcheck (username, attribute, op, value) VALUES (?, 'Cleartext-Password', ':=', ?)");
                        $stmt2->bind_param("ss", $username, $password);
                        $stmt2->execute();
                        
                        // Update username di miniradius_user_status jika berubah
                        if ($old_username !== $username) {
                            $stmtStatusUpdate = $conn->prepare("UPDATE miniradius_user_status SET username = ? WHERE username = ?");
                            if ($stmtStatusUpdate) {
                                $stmtStatusUpdate->bind_param("ss", $username, $old_username);
                                $stmtStatusUpdate->execute();
                                $stmtStatusUpdate->close();
                            }
                        }
                        
                        // Delete old profile
                        $stmt3 = $conn->prepare("DELETE FROM radusergroup WHERE username = ?");
                        $stmt3->bind_param("s", $old_username);
                        $stmt3->execute();
                        
                        // Insert new profile
                        if (!empty($profile)) {
                            $stmt4 = $conn->prepare("INSERT INTO radusergroup (username, groupname, priority) VALUES (?, ?, 1)");
                            $stmt4->bind_param("ss", $username, $profile);
                            $stmt4->execute();
                        }
                        $conn->commit();
                        $alert_msg = 'User berhasil diperbarui di database!';
                    }
                } catch (Exception $e) {
                    $conn->rollback();
                    $alert_msg = 'Gagal menyimpan user: ' . $e->getMessage();
                    $alert_type = 'error';
                }
            }
        }
    }
    
    elseif ($action === 'delete_user') {
        $username = trim($_POST['username'] ?? '');
        if ($is_mock) {
            foreach ($_SESSION['mock_users'] as $k => $u) {
                if ($u['username'] === $username) {
                    unset($_SESSION['mock_users'][$k]);
                    break;
                }
            }
            $_SESSION['mock_users'] = array_values($_SESSION['mock_users']); // reindex
            $alert_msg = 'User berhasil dihapus!';
        } else {
            $conn->begin_transaction();
            try {
                $stmt1 = $conn->prepare("DELETE FROM radcheck WHERE username = ?");
                $stmt1->bind_param("s", $username);
                $stmt1->execute();
                
                $stmt2 = $conn->prepare("DELETE FROM radusergroup WHERE username = ?");
                $stmt2->bind_param("s", $username);
                $stmt2->execute();
                
                $stmt3 = $conn->prepare("DELETE FROM miniradius_user_status WHERE username = ?");
                if ($stmt3) {
                    $stmt3->bind_param("s", $username);
                    $stmt3->execute();
                    $stmt3->close();
                }
                
                $conn->commit();
                $alert_msg = 'User berhasil dihapus dari database!';
            } catch (Exception $e) {
                $conn->rollback();
                $alert_msg = 'Gagal menghapus user: ' . $e->getMessage();
                $alert_type = 'error';
            }
        }
    }
    
    elseif ($action === 'toggle_user_status') {
        $username = trim($_POST['username'] ?? '');
        if ($is_mock) {
            foreach ($_SESSION['mock_users'] as &$u) {
                if ($u['username'] === $username) {
                    $u['status'] = ($u['status'] ?? 'active') === 'disabled' ? 'active' : 'disabled';
                    $alert_msg = 'Status user berhasil diubah menjadi ' . ucfirst($u['status']) . '!';
                    break;
                }
            }
        } else {
            try {
                // Baca status dari tabel terpisah (aman, tidak disentuh FreeRADIUS)
                $current_status = 'active';
                $stmt = $conn->prepare("SELECT status FROM miniradius_user_status WHERE username = ? LIMIT 1");
                if ($stmt) {
                    $stmt->bind_param("s", $username);
                    $stmt->execute();
                    $stmt->bind_result($current_status);
                    $stmt->fetch();
                    $stmt->close();
                }

                $current_status = $current_status ?? 'active';
                $new_status = ($current_status === 'disabled') ? 'active' : 'disabled';
                
                $stmtUpsert = $conn->prepare("INSERT INTO miniradius_user_status (username, status) VALUES (?, ?) ON DUPLICATE KEY UPDATE status = VALUES(status)");
                if ($stmtUpsert) {
                    $stmtUpsert->bind_param("ss", $username, $new_status);
                    $stmtUpsert->execute();
                    $stmtUpsert->close();
                }
                $alert_msg = 'Status user berhasil diubah menjadi ' . ucfirst($new_status) . '!';
            } catch (Exception $e) {
                $alert_msg = 'Gagal mengubah status user: ' . $e->getMessage();
                $alert_type = 'error';
            }
        }
    }
    
    // --- PROFILE CRUD HANDLERS ---
    elseif ($action === 'add_profile' || $action === 'edit_profile') {
        $service = trim($_POST['service'] ?? 'PPPoE');
        $groupname_input = trim($_POST['groupname'] ?? '');
        
        // Strip any manual prefix typed by the user to avoid double prefix (e.g. PPPoE_PPPoE_10M)
        $groupname_cleaned = preg_replace('/^(pppoe|hotspot)_/i', '', $groupname_input);
        $groupname = $service . '_' . $groupname_cleaned;
        
        $rate_limit = trim($_POST['rate_limit'] ?? '');
        $old_groupname = trim($_POST['old_groupname'] ?? '');
        
        if (empty($groupname) || empty($rate_limit)) {
            $alert_msg = 'Nama Profile dan Speed Limit wajib diisi!';
            $alert_type = 'error';
        } else {
            if ($is_mock) {
                if ($action === 'add_profile') {
                    if (isset($_SESSION['mock_profiles'][$groupname])) {
                        $alert_msg = 'Profile sudah terdaftar!';
                        $alert_type = 'error';
                    } else {
                        $_SESSION['mock_profiles'][$groupname] = $rate_limit;
                        $alert_msg = 'Profile baru berhasil ditambahkan!';
                    }
                } else { // edit profile
                    if ($old_groupname !== $groupname) {
                        unset($_SESSION['mock_profiles'][$old_groupname]);
                    }
                    $_SESSION['mock_profiles'][$groupname] = $rate_limit;
                    
                    // Update user profiles referencing this
                    foreach ($_SESSION['mock_users'] as &$u) {
                        if ($u['profile'] === $old_groupname) {
                            $u['profile'] = $groupname;
                        }
                    }
                    $alert_msg = 'Profile berhasil diperbarui!';
                }
            } else {
                $conn->begin_transaction();
                try {
                    if ($action === 'add_profile') {
                        $stmt = $conn->prepare("INSERT INTO radgroupreply (groupname, attribute, op, value) VALUES (?, 'Mikrotik-Rate-Limit', ':=', ?)");
                        $stmt->bind_param("ss", $groupname, $rate_limit);
                        $stmt->execute();
                        $conn->commit();
                        $alert_msg = 'Profile baru berhasil disimpan ke database!';
                    } else { // edit profile
                        // Delete old profile check
                        $stmt1 = $conn->prepare("DELETE FROM radgroupreply WHERE groupname = ? AND attribute = 'Mikrotik-Rate-Limit'");
                        $stmt1->bind_param("s", $old_groupname);
                        $stmt1->execute();
                        
                        // Insert new rate limit
                        $stmt2 = $conn->prepare("INSERT INTO radgroupreply (groupname, attribute, op, value) VALUES (?, 'Mikrotik-Rate-Limit', ':=', ?)");
                        $stmt2->bind_param("ss", $groupname, $rate_limit);
                        $stmt2->execute();
                        
                        // Update user group references
                        $stmt3 = $conn->prepare("UPDATE radusergroup SET groupname = ? WHERE groupname = ?");
                        $stmt3->bind_param("ss", $groupname, $old_groupname);
                        $stmt3->execute();
                        
                        $conn->commit();
                        $alert_msg = 'Profile berhasil diperbarui di database!';
                    }
                } catch (Exception $e) {
                    $conn->rollback();
                    $alert_msg = 'Gagal menyimpan profile: ' . $e->getMessage();
                    $alert_type = 'error';
                }
            }
        }
    }
    
    elseif ($action === 'delete_profile') {
        $groupname = trim($_POST['groupname'] ?? '');
        if ($is_mock) {
            unset($_SESSION['mock_profiles'][$groupname]);
            // Clear users referencing this profile
            foreach ($_SESSION['mock_users'] as &$u) {
                if ($u['profile'] === $groupname) {
                    $u['profile'] = '';
                }
            }
            $alert_msg = 'Profile berhasil dihapus!';
        } else {
            $conn->begin_transaction();
            try {
                $stmt1 = $conn->prepare("DELETE FROM radgroupreply WHERE groupname = ?");
                $stmt1->bind_param("s", $groupname);
                $stmt1->execute();
                
                // Clear profile reference from user mapping
                $stmt2 = $conn->prepare("UPDATE radusergroup SET groupname = '' WHERE groupname = ?");
                $stmt2->bind_param("s", $groupname);
                $stmt2->execute();
                
                $conn->commit();
                $alert_msg = 'Profile berhasil dihapus dari database!';
            } catch (Exception $e) {
                $conn->rollback();
                $alert_msg = 'Gagal menghapus profile: ' . $e->getMessage();
                $alert_type = 'error';
            }
        }
    }
    
    // --- SAVE SETTINGS HANDLER ---
    elseif ($action === 'save_settings') {
        $config['db_host'] = trim($_POST['db_host'] ?? '');
        $config['db_user'] = trim($_POST['db_user'] ?? '');
        $config['db_pass'] = trim($_POST['db_pass'] ?? '');
        $config['db_name'] = trim($_POST['db_name'] ?? '');
        
        $config['mkt_ip'] = trim($_POST['mkt_ip'] ?? '');
        $config['mkt_port'] = trim($_POST['mkt_port'] ?? '');
        $config['mkt_user'] = trim($_POST['mkt_user'] ?? '');
        $config['mkt_pass'] = trim($_POST['mkt_pass'] ?? '');
        $config['mkt_protocol'] = trim($_POST['mkt_protocol'] ?? 'http');
        
        $config['nas_secret'] = trim($_POST['nas_secret'] ?? '');
        $config['radius_log_path'] = trim($_POST['radius_log_path'] ?? '');
        $config['nas_ip'] = $config['mkt_ip'];
        $config['nas_type'] = 'mikrotik';
        
        $config['mock_mode'] = isset($_POST['mock_mode']);
        
        // Write config parameters back to config.php
        $config_content = "<?php\n// MiniRadius Configuration File\nreturn " . var_export($config, true) . ";\n";
        if (file_put_contents($config_path, $config_content) !== false) {
            $alert_msg = 'Pengaturan koneksi berhasil disimpan!';
            // Refresh variables
            $is_mock = $config['mock_mode'];
            if (!$is_mock) {
                try {
                    $conn = new mysqli($config['db_host'], $config['db_user'], $config['db_pass'], $config['db_name']);
                    if (!$conn->connect_error) {
                        $db_connected = true;
                        
                        // Sync NAS configuration ke database
                        $nas_stmt = $conn->prepare(
                            "INSERT INTO nas (nasname, shortname, type, secret, description) VALUES (?, ?, ?, ?, ?)
                             ON DUPLICATE KEY UPDATE shortname = VALUES(shortname), type = VALUES(type), secret = VALUES(secret), description = VALUES(description)"
                        );
                        if ($nas_stmt) {
                            $nas_ip = $config['mkt_ip'] ?? '';
                            $nas_shortname = 'MikroTik-' . preg_replace('/[^a-zA-Z0-9]/', '', $nas_ip);
                            $nas_type = $config['nas_type'] ?? 'mikrotik';
                            $nas_secret = $config['nas_secret'] ?? '';
                            $nas_desc = 'MikroTik RouterOS v7 - MiniRadius';
                            $nas_stmt->bind_param("sssss", $nas_ip, $nas_shortname, $nas_type, $nas_secret, $nas_desc);
                            $nas_stmt->execute();
                            $nas_stmt->close();
                        }
                    }
                } catch (Exception $e) {}
            }
        } else {
            $alert_msg = 'Gagal menulis file config.php. Periksa izin folder Anda.';
            $alert_type = 'error';
        }
    }

    // Save alert to session and redirect (PRG pattern) to prevent resubmission on page refresh
    if (!empty($alert_msg)) {
        $_SESSION['alert_msg'] = $alert_msg;
        $_SESSION['alert_type'] = $alert_type;
    }
    
    $redirect_url = $_SERVER['REQUEST_URI'] ?? ('index.php' . (isset($_GET['page']) ? '?page=' . urlencode($_GET['page']) : ''));
    header("Location: " . $redirect_url);
    exit;
}

// 5. FETCH DATA FOR ACTIVE VIEW
$page = $_GET['page'] ?? 'dashboard';

// MikroTik Router Resource Variables (Default mock values)
$mkt_board_name = 'L009UiGS-2ndD';
$mkt_cpu_model = 'ARM';
$mkt_cpu_freq = '800MHz';
$mkt_total_mem = 512 * 1024 * 1024;
$mkt_free_mem = 382 * 1024 * 1024;
$mkt_uptime = '5d 12h 43m';
$mkt_cpu_temp = 42;
$mkt_cpu_load = 14;

if (!$is_mock) {
    // Query system resource
    $res_resource = miniradius_get_mikrotik_data($config, '/system/resource');
    if ($res_resource) {
        $res_val = isset($res_resource[0]) ? $res_resource[0] : $res_resource;
        $mkt_board_name = $res_val['board-name'] ?? $mkt_board_name;
        $mkt_cpu_model = $res_val['cpu'] ?? $mkt_cpu_model;
        $mkt_cpu_freq = isset($res_val['cpu-frequency']) ? ($res_val['cpu-frequency'] . 'MHz') : $mkt_cpu_freq;
        $mkt_total_mem = (float)($res_val['total-memory'] ?? $mkt_total_mem);
        $mkt_free_mem = (float)($res_val['free-memory'] ?? $mkt_free_mem);
        
        if (isset($res_val['uptime'])) {
            $mkt_uptime = preg_replace('/([dhms])/', '$1 ', $res_val['uptime']);
            $mkt_uptime = trim($mkt_uptime);
        }
        $mkt_cpu_load = (int)($res_val['cpu-load'] ?? $mkt_cpu_load);
    }
    
    // Query CPU temperature
    $res_health = miniradius_get_mikrotik_data($config, '/system/health');
    if ($res_health && is_array($res_health)) {
        foreach ($res_health as $item) {
            $name = strtolower($item['name'] ?? '');
            if ($name === 'cpu-temperature' || $name === 'temperature' || $name === 'cpu_temperature') {
                $mkt_cpu_temp = (int)($item['value'] ?? $mkt_cpu_temp);
                break;
            }
        }
    }
}

$mkt_free_mem_mb = round($mkt_free_mem / (1024 * 1024), 0);
$mkt_total_mem_mb = round($mkt_total_mem / (1024 * 1024), 0);
$mkt_free_mem_pct = $mkt_total_mem > 0 ? round(($mkt_free_mem / $mkt_total_mem) * 100, 1) : 0;

// Dynamic Database Fetching vs Session Mock Fetching
$users_list = [];
$profiles_list = [];
$logs_list = [];

if ($is_mock) {
    // 1. Users List
    $users_list = $_SESSION['mock_users'];
    
    // 2. Profiles List
    foreach ($_SESSION['mock_profiles'] as $g => $v) {
        $profiles_list[] = ['groupname' => $g, 'rate_limit' => $v];
    }
    
    // 3. System Logs
    $logs_list = $_SESSION['mock_logs'];
    
    // 4. Summaries
    $active_pppoe = 0;
    $active_hotspot = 0;
    foreach ($logs_list as $l) {
        if ($l['status'] === 'Online') {
            if ($l['protocol'] === 'PPPoE') $active_pppoe++;
            else $active_hotspot++;
        }
    }
    $online_users = $active_pppoe + $active_hotspot;
} else {
    // Real FreeRADIUS Queries
    
    // 1. Active metrics
    $res = $conn->query("SELECT COUNT(*) AS total FROM radacct WHERE acctstoptime IS NULL AND framedprotocol IN ('PPPoE', 'PPP')");
    $active_pppoe = $res ? (int)$res->fetch_assoc()['total'] : 0;
    
    $res = $conn->query("SELECT COUNT(*) AS total FROM radacct WHERE acctstoptime IS NULL AND (framedprotocol NOT IN ('PPPoE', 'PPP') OR framedprotocol IS NULL)");
    $active_hotspot = $res ? (int)$res->fetch_assoc()['total'] : 0;
    
    $online_users = $active_pppoe + $active_hotspot;
    
    // 2. Load Profiles
    $res = $conn->query("SELECT DISTINCT groupname, value FROM radgroupreply WHERE attribute = 'Mikrotik-Rate-Limit'");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $profiles_list[] = ['groupname' => $row['groupname'], 'rate_limit' => $row['value']];
        }
    }
    
    // 3. Load Users (status dari tabel terpisah, aman dari FreeRADIUS)
    $res = $conn->query("SELECT rc.username, rc.value AS password, rug.groupname AS profile, COALESCE(mus.status, 'active') AS status 
                         FROM radcheck rc 
                         LEFT JOIN radusergroup rug ON rc.username = rug.username 
                         LEFT JOIN miniradius_user_status mus ON rc.username = mus.username 
                         WHERE rc.attribute = 'Cleartext-Password'");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $users_list[] = [
                'username' => $row['username'],
                'password' => $row['password'],
                'profile' => !empty($row['profile']) ? $row['profile'] : '',
                'status' => !empty($row['status']) ? $row['status'] : 'active'
            ];
        }
    }
    
    // 4. Load Login Logs (radacct — sesi login user)
    $res = $conn->query("SELECT username, framedipaddress, callingstationid, acctstarttime, acctstoptime,
                                acctinputoctets, acctoutputoctets, acctsessiontime
                         FROM radacct ORDER BY acctstarttime DESC LIMIT 50");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $logs_list[] = [
                'username' => $row['username'],
                'ip_address' => !empty($row['framedipaddress']) ? $row['framedipaddress'] : '-',
                'mac_address' => !empty($row['callingstationid']) ? $row['callingstationid'] : '-',
                'login_time' => $row['acctstarttime'],
                'status' => is_null($row['acctstoptime']) ? 'Online' : 'Offline',
            ];
        }
    }
}

// 6. LOAD FREERADIUS LOG LINES (for Log Viewer page)
$radius_log_lines = [];
$radius_log_error = '';
if ($is_mock) {
    // Generate realistic simulated FreeRADIUS log entries
    $now = time();
    $radius_log_lines = include __DIR__ . '/data/mock_radius_logs.php';
} else {
    // Real mode: read the last 150 lines of the FreeRADIUS log file
    $log_path = $config['radius_log_path'] ?? '/var/log/freeradius/radius.log';
    if (file_exists($log_path) && is_readable($log_path)) {
        $all_lines = file($log_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $radius_log_lines = array_slice($all_lines, -150);
    } elseif (!file_exists($log_path)) {
        $radius_log_error = "File log tidak ditemukan: <code>" . htmlspecialchars($log_path) . "</code>. Periksa konfigurasi path di Connection Setting.";
    } else {
        $radius_log_error = "Tidak dapat membaca file log: <code>" . htmlspecialchars($log_path) . "</code>. Pastikan web server memiliki izin baca file.";
    }
}

// Fallback: Jika radacct kosong (0 online) tapi ada user aktif, tampilkan data user
if ($online_users === 0 && count($users_list) > 0) {
    $pppoe_count = 0;
    $hotspot_count = 0;
    foreach ($users_list as $u) {
        if (($u['status'] ?? 'active') !== 'active') continue;
        if (stripos($u['profile'], 'pppoe') !== false) $pppoe_count++;
        else $hotspot_count++; // hotspot atau tanpa profile
    }
    if ($pppoe_count > 0 || $hotspot_count > 0) {
        $active_pppoe = $pppoe_count;
        $active_hotspot = $hotspot_count;
        $online_users = $pppoe_count + $hotspot_count;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MiniRadius - Panel Radius Mikrotik</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="assets/style.css">
    <script src="assets/script.js" defer></script>
</head>
<body class="bg-cream-200 text-slate-800 min-h-screen flex flex-col font-sans">

    <!-- Floating Notification Container -->
    <div id="notification-container" class="fixed top-5 right-5 z-[9999] flex flex-col gap-3 w-full max-w-sm pointer-events-none"></div>

    <!-- Mobile Header Navigation Drawer Button -->
    <div class="lg:hidden bg-brand-500 border-b border-brand-600 px-4 py-3 flex items-center justify-between sticky top-0 z-40">
        <div class="flex items-center gap-2">
            <div class="bg-white/20 p-2 rounded-lg text-white">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                </svg>
            </div>
            <span class="font-bold text-white tracking-tight">MiniRadius</span>
        </div>
        <button id="menu-btn" class="text-white/80 p-1 rounded-md hover:text-white hover:bg-white/10 transition-colors">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
            </svg>
        </button>
    </div>

    <div class="flex flex-1 relative overflow-hidden">
        
        <!-- SIDEBAR NAVIGATION -->
        <aside id="sidebar" class="fixed inset-y-0 left-0 transform -translate-x-full lg:translate-x-0 lg:static transition-transform duration-300 ease-in-out z-50 w-64 bg-brand-500 flex flex-col justify-between shrink-0 h-screen lg:h-auto">
            <div>
                <!-- Brand logo in Sidebar -->
                <div class="hidden lg:flex items-center gap-3 px-6 py-6 border-b border-white/15">
                    <div class="bg-white/20 p-2.5 rounded-xl">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-base font-bold text-white tracking-tight">MiniRadius</h2>
                        <p class="text-[10px] text-white/60">RouterOS v7.23 + FreeRADIUS</p>
                    </div>
                </div>

                <!-- Navigation Menu Links -->
                <nav class="mt-4 px-3 space-y-1">
                    
                    <!-- Dashboard -->
                    <a href="?page=dashboard" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all font-medium text-sm <?php echo $page === 'dashboard' ? 'bg-white/20 border border-white/25 text-white' : 'text-white/70 hover:bg-white/10 hover:text-white border border-transparent'; ?>">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2v-4zM14 16a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2v-4z"></path>
                        </svg>
                        Dashboard
                    </a>
                    
                    <!-- User Management -->
                    <a href="?page=users" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all font-medium text-sm <?php echo $page === 'users' ? 'bg-white/20 border border-white/25 text-white' : 'text-white/70 hover:bg-white/10 hover:text-white border border-transparent'; ?>">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 00-2 2v1c0 .6.4 1 1 1h12c.6 0 1-.4 1-1v-1a2 2 0 00-2-2m-6 0a2 2 0 00-2 2m6-2a2 2 0 00-2 2M6 12H4m2 0a2 2 0 100 4m0-4a2 2 0 110 4m14-4h-2m2 0a2 2 0 100 4m0-4a2 2 0 110 4"></path>
                        </svg>
                        Manajemen User
                    </a>
                    
                    <!-- Profile Editor -->
                    <a href="?page=profiles" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all font-medium text-sm <?php echo $page === 'profiles' ? 'bg-white/20 border border-white/25 text-white' : 'text-white/70 hover:bg-white/10 hover:text-white border border-transparent'; ?>">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        Profile Editor
                    </a>
                    
                    <!-- Hotspot Users -->
                    <a href="?page=hotspot" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all font-medium text-sm <?php echo $page === 'hotspot' ? 'bg-white/20 border border-white/25 text-white' : 'text-white/70 hover:bg-white/10 hover:text-white border border-transparent'; ?>">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071a10.5 10.5 0 0114.14 0M1.34 6.344a16.5 16.5 0 0122.656 0"></path>
                        </svg>
                        User Hotspot
                    </a>
                    
                    <!-- PPPoE Users -->
                    <a href="?page=pppoe" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all font-medium text-sm <?php echo $page === 'pppoe' ? 'bg-white/20 border border-white/25 text-white' : 'text-white/70 hover:bg-white/10 hover:text-white border border-transparent'; ?>">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v5H7a2 2 0 00-2 2v4h14v-4a2 2 0 00-2-2h-2V3m-4 5h4m-4 7h4m-2 0v6" />
                        </svg>
                        User PPPoE
                    </a>
                    
                    <!-- Connection Setting -->
                    <a href="?page=settings" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all font-medium text-sm <?php echo $page === 'settings' ? 'bg-white/20 border border-white/25 text-white' : 'text-white/70 hover:bg-white/10 hover:text-white border border-transparent'; ?>">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                        Connection Setting
                    </a>

                    <!-- System Log -->
                    <a href="?page=logs" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all font-medium text-sm <?php echo $page === 'logs' ? 'bg-white/20 border border-white/25 text-white' : 'text-white/70 hover:bg-white/10 hover:text-white border border-transparent'; ?>">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        Log Sistem
                    </a>
                </nav>
            </div>

            <!-- Sidebar Footer details -->
            <div class="p-4 border-t border-white/15 bg-brand-600/30 text-[11px] text-white/60 flex flex-col gap-1">
                <div class="flex items-center gap-1.5">
                    <span class="h-1.5 w-1.5 rounded-full <?php echo $is_mock ? 'bg-amber-300' : 'bg-emerald-300'; ?>"></span>
                    <span class="font-medium text-white/80"><?php echo $is_mock ? 'Demo Mode' : 'Connected to DB'; ?></span>
                </div>
                <div>L009 IP: <span class="font-mono text-white/80"><?php echo htmlspecialchars($config['mkt_ip']); ?></span></div>
            </div>
        </aside>

        <!-- Dynamic Content Section -->
        <main class="flex-grow p-6 overflow-y-auto w-full max-w-7xl mx-auto flex flex-col gap-3">
            
            <!-- Global Alert Banners -->
            <?php if (!empty($alert_msg)): ?>
                <script>
                    document.addEventListener('DOMContentLoaded', () => {
                        window.showNotification(<?php echo json_encode($alert_msg); ?>, <?php echo json_encode($alert_type); ?>);
                    });
                </script>
            <?php endif; ?>

            <?php if ($is_mock && $page !== 'settings'): ?>
                <div class="bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3.5 rounded-xl flex items-center justify-between text-xs">
                    <div class="flex items-center gap-2.5">
                        <span class="bg-amber-100 p-1.5 rounded-lg text-amber-600">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </span>
                        <span>
                            <strong>Demo Mode (Mock data)</strong> — CRUD data tersimpan di PHP Session.
                            <?php if (!empty($db_error)): ?>
                                <br><span class="text-rose-600 font-semibold mt-1 inline-block">Koneksi DB gagal: <?php echo htmlspecialchars($db_error); ?></span>
                                <br>Perbaiki konfigurasi di menu <strong>Connection Setting</strong>.
                            <?php else: ?>
                                Konfigurasi Database Anda di menu <strong>Connection Setting</strong> untuk beralih ke Database Real.
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
            <?php endif; ?>

            <!-- ROUTING VIEWS -->
            <?php if ($page === 'dashboard'): ?>
                <!-- =======================================
                     VIEW: DASHBOARD
                     ======================================= -->
                <!-- Summary Metrics Box Grid -->
                <?php $total_registered = count($users_list); ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
                    <!-- PPPoE -->
                    <div class="relative overflow-hidden bg-white border border-cream-300 rounded-2xl p-6 shadow-sm group hover:shadow-md hover:border-brand-200 transition-all duration-300">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Active PPPoE Users</p>
                                <h3 class="text-3xl font-extrabold text-slate-800 mt-2" id="val-pppoe"><?php echo $active_pppoe; ?></h3>
                                <p class="text-xs text-brand-500 font-medium mt-1">PPPoE Dial-in Tunnels</p>
                            </div>
                            <div class="bg-brand-50 border border-brand-100 p-3.5 rounded-2xl text-brand-500 group-hover:scale-105 transition-all">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8V4m0 4a4 4 0 014 4v6a2 2 0 01-2 2h-4a2 2 0 01-2-2v-6a4 4 0 014-4zm0 12v2m-3-2h6" />
                                </svg>
                            </div>
                        </div>
                    </div>
                    <!-- Hotspot -->
                    <div class="relative overflow-hidden bg-white border border-cream-300 rounded-2xl p-6 shadow-sm group hover:shadow-md hover:border-brand-200 transition-all duration-300">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Active Hotspot Users</p>
                                <h3 class="text-3xl font-extrabold text-slate-800 mt-2" id="val-hotspot"><?php echo $active_hotspot; ?></h3>
                                <p class="text-xs text-teal-600 font-medium mt-1">Portal Authentication</p>
                            </div>
                            <div class="bg-teal-50 border border-teal-100 p-3.5 rounded-2xl text-teal-600 group-hover:scale-105 transition-all">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071a10.5 10.5 0 0114.14 0M1.34 6.344a16.5 16.5 0 0122.656 0"></path>
                                </svg>
                            </div>
                        </div>
                    </div>
                    <!-- Total Online -->
                    <div class="relative overflow-hidden bg-white border border-cream-300 rounded-2xl p-6 shadow-sm group hover:shadow-md hover:border-brand-200 transition-all duration-300">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Total Online Users</p>
                                <h3 class="text-3xl font-extrabold text-slate-800 mt-2" id="val-online"><?php echo $online_users; ?></h3>
                                <p class="text-xs text-emerald-600 font-medium mt-1 flex items-center gap-1.5">
                                    <span class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                    Live sessions online
                                </p>
                            </div>
                            <div class="bg-emerald-50 border border-emerald-100 p-3.5 rounded-2xl text-emerald-600 group-hover:scale-105 transition-all">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                                </svg>
                            </div>
                        </div>
                    </div>
                    <!-- Total Registered -->
                    <div class="relative overflow-hidden bg-white border border-cream-300 rounded-2xl p-6 shadow-sm group hover:shadow-md hover:border-brand-200 transition-all duration-300">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Total Registered Users</p>
                                <h3 class="text-3xl font-extrabold text-slate-800 mt-2"><?php echo $total_registered; ?></h3>
                                <p class="text-xs text-indigo-600 font-medium mt-1">Akun terdaftar di sistem</p>
                            </div>
                            <div class="bg-indigo-50 border border-indigo-100 p-3.5 rounded-2xl text-indigo-600 group-hover:scale-105 transition-all">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Live Traffic Graphic Monitor & CPU -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-3">
                    <!-- Bandwidth Graph -->
                    <div class="lg:col-span-2 bg-white border border-cream-300 rounded-2xl p-6 shadow-sm flex flex-col justify-between">
                        <div>
                            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
                                <div>
                                    <h3 class="text-base font-bold text-slate-800">Live Traffic Monitor</h3>
                                    <p class="text-xs text-slate-500">Interface bandwidth updates in real-time</p>
                                </div>
                                <div class="flex items-center gap-6">
                                    <div class="flex items-center gap-2">
                                        <span class="h-3 w-3 rounded-full bg-brand-500 inline-block shadow-lg shadow-brand-500/30"></span>
                                        <div>
                                            <p class="text-[10px] text-slate-500 font-semibold uppercase leading-none">Download (Rx)</p>
                                            <p class="text-base font-extrabold text-slate-800 mt-1" id="speed-dl">0.0 Mbps</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="h-3 w-3 rounded-full bg-teal-500 inline-block shadow-lg shadow-teal-500/30"></span>
                                        <div>
                                            <p class="text-[10px] text-slate-500 font-semibold uppercase leading-none">Upload (Tx)</p>
                                            <p class="text-base font-extrabold text-slate-800 mt-1" id="speed-ul">0.0 Mbps</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Canvas -->
                            <div class="relative w-full h-[250px]">
                                <canvas id="trafficChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <!-- Router Resources -->
                    <div class="bg-white border border-cream-300 rounded-2xl p-6 shadow-sm flex flex-col justify-between">
                        <div>
                             <h3 class="text-base font-bold text-slate-800 mb-1">Router Resources</h3>
                            <p class="text-xs text-slate-500 mb-6">MikroTik <?php echo htmlspecialchars($mkt_board_name); ?> Resources</p>
                            
                            <div class="space-y-5">
                                <div>
                                    <div class="flex justify-between text-xs font-semibold mb-1.5">
                                        <span class="text-slate-600">CPU Load</span>
                                        <span class="text-brand-500" id="gauge-cpu-txt"><?php echo $mkt_cpu_load; ?>%</span>
                                    </div>
                                    <div class="w-full bg-cream-200 border border-cream-300 h-2.5 rounded-full overflow-hidden">
                                        <div id="gauge-cpu-bar" class="h-full bg-gradient-to-r from-brand-500 to-brand-400 rounded-full transition-all duration-1000" style="width: <?php echo $mkt_cpu_load; ?>%"></div>
                                    </div>
                                </div>
                                <div>
                                    <div class="flex justify-between text-xs font-semibold mb-1.5">
                                        <span class="text-slate-600">Memory Free</span>
                                        <span class="text-emerald-600" id="gauge-mem-txt"><?php echo $mkt_free_mem_mb; ?> MB / <?php echo $mkt_total_mem_mb; ?> MB</span>
                                    </div>
                                    <div class="w-full bg-cream-200 border border-cream-300 h-2.5 rounded-full overflow-hidden">
                                        <div id="gauge-mem-bar" class="h-full bg-gradient-to-r from-emerald-500 to-teal-500 rounded-full" style="width: <?php echo $mkt_free_mem_pct; ?>%"></div>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between py-2 border-b border-cream-300">
                                    <span class="text-xs text-slate-500">CPU Temperature</span>
                                    <span class="text-sm font-semibold text-slate-700 flex items-center gap-1">
                                        <svg class="w-4 h-4 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                        </svg>
                                        <span id="gauge-temp-txt"><?php echo $mkt_cpu_temp; ?></span>°C
                                    </span>
                                </div>
                                <div class="flex items-center justify-between py-2 border-b border-cream-300">
                                    <span class="text-xs text-slate-500">Uptime Router</span>
                                    <span class="text-sm font-semibold text-slate-700" id="gauge-uptime-txt"><?php echo htmlspecialchars($mkt_uptime); ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="mt-6 pt-4 border-t border-cream-300 flex items-center justify-between text-[11px] text-slate-400 font-mono">
                            <span id="gauge-platform-txt">Platform: <?php echo htmlspecialchars($mkt_board_name); ?></span>
                            <span id="gauge-cpu-model-txt">CPU: <?php echo htmlspecialchars($mkt_cpu_model); ?> <?php echo htmlspecialchars($mkt_cpu_freq); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Recent Login Table -->
                <div class="bg-white border border-cream-300 rounded-2xl overflow-hidden shadow-xl">
                    <div class="p-6 border-b border-cream-300 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 class="text-base font-bold text-slate-800">Log Login Baru-Baru Ini</h3>
                            <p class="text-xs text-slate-500">Pengguna yang baru saja terhubung melalui RADIUS</p>
                        </div>
                        <div class="w-full sm:w-72">
                            <input id="dashboard-log-search" type="search" oninput="filterTable(this.value, 'dashboard-log-table')" placeholder="Cari log..." class="w-full bg-cream-100 border border-cream-300 focus:border-brand-500/50 rounded-xl px-4 py-2.5 text-sm text-slate-800 placeholder:text-slate-400 focus:outline-none transition-colors">
                        </div>
                    </div>
                    <div class="relative overflow-x-auto max-h-[700px] overflow-y-auto" data-scrollable-table="dashboard-log-table">
                        <table id="dashboard-log-table" class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-cream-100 text-slate-500 text-[10px] uppercase font-bold tracking-wider border-b border-cream-300">
                                    <th class="py-3.5 px-6">Username</th>
                                    <th class="py-3.5 px-6">IP Address</th>
                                    <th class="py-3.5 px-6">MAC Address</th>
                                    <th class="py-3.5 px-6">Login Time</th>
                                    <th class="py-3.5 px-6 text-right">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-cream-200 text-sm">
                                <?php 
                                // Gunakan logs_list jika ada, fallback ke users_list
                                $recent_dashboard_logs = array_slice($logs_list, 0, 5);
                                if (count($recent_dashboard_logs) === 0 && count($users_list) > 0) {
                                    $recent_dashboard_logs = array_slice($users_list, 0, 5);
                                }
                                if (count($recent_dashboard_logs) > 0): 
                                    foreach ($recent_dashboard_logs as $log): 
                                        $is_from_users = !isset($log['ip_address']); // cek apakah dari users_list
                                ?>
                                    <tr class="hover:bg-cream-50 transition-colors">
                                        <td class="py-3 px-6 font-semibold text-slate-700">
                                            <div class="flex items-center gap-2">
                                                <div class="w-7 h-7 rounded-lg bg-cream-100 border border-cream-300 flex items-center justify-center text-slate-400">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                                    </svg>
                                                </div>
                                                <span><?php echo htmlspecialchars($log['username'] ?? $log['username']); ?></span>
                                            </div>
                                        </td>
                                        <td class="py-3 px-6 font-mono text-xs text-slate-600"><?php echo htmlspecialchars($log['ip_address'] ?? ($is_from_users ? '-' : '-')); ?></td>
                                        <td class="py-3 px-6 font-mono text-xs text-slate-500"><?php echo htmlspecialchars($log['mac_address'] ?? ($is_from_users ? '-' : '-')); ?></td>
                                        <td class="py-3 px-6 text-xs text-slate-600"><?php echo $is_from_users ? '<span class="text-slate-400 italic">Terdaftar</span>' : date('d M Y, H:i:s', strtotime($log['login_time'])); ?></td>
                                        <td class="py-3 px-6 text-right">
                                            <div class="inline-flex justify-end w-full">
                                                <?php 
                                                $status = $is_from_users ? ($log['status'] ?? 'active') : $log['status'];
                                                if ($status === 'Online' || $status === 'active'): 
                                                ?>
                                                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                                        <?php echo $is_from_users ? 'Aktif' : 'Online'; ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-semibold bg-cream-100 text-slate-500 border border-cream-300">
                                                        <?php echo $is_from_users ? 'Nonaktif' : 'Offline'; ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php 
                                    endforeach;
                                else: 
                                ?>
                                    <tr>
                                        <td colspan="5" class="py-8 text-center text-slate-500 font-medium">
                                            Belum ada sesi login RADIUS.
                                            <?php if ($is_mock): ?>
                                                <br><span class="text-xs">Data mock akan muncul setelah halaman di-refresh.</span>
                                            <?php else: ?>
                                                <br><span class="text-xs">Sesi akan muncul setelah user connect via PPPoE/Hotspot.</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        <div class="scroll-indicator scroll-indicator-down absolute bottom-2 right-4 text-[10px] px-2 py-1 rounded bg-slate-700/80 text-slate-300 pointer-events-none opacity-0 transition-opacity duration-300 flex items-center gap-1">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path></svg>
                            Scroll
                        </div>
                    </div>
                </div>

            <?php elseif ($page === 'users'): ?>
                <!-- =======================================
                     VIEW: USER MANAGEMENT (CRUD FOR USERS)
                     ======================================= -->
                <div class="flex flex-col gap-1">
                    <h2 class="text-xl font-bold text-slate-800">Manajemen User</h2>
                    <p class="text-xs text-slate-500">Atur pengguna FreeRADIUS dan pemetaan profile kecepatannya</p>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-3">
                    <!-- Column 1: Add/Edit Forms -->
                    <div class="space-y-3">
                        
                        <!-- Form 1: User Form -->
                        <div class="bg-white border border-cream-300 rounded-2xl p-6 shadow-xl">
                            <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider mb-4" id="user-form-title">Tambah User</h3>
                            
                            <form method="POST" action="" class="space-y-4" autocomplete="off">
                                <input type="hidden" name="action" id="user-action" value="add_user">
                                <input type="hidden" name="old_username" id="user-old-username" value="">
                                
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 mb-1.5">Username</label>
                                    <input type="text" name="username" id="user-username" placeholder="Masukkan username" required autocomplete="off" class="w-full bg-cream-100 border border-cream-300 focus:border-brand-500/50 rounded-xl px-4 py-2.5 text-sm text-slate-800 placeholder:text-slate-400 focus:outline-none transition-colors">
                                </div>
                                
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 mb-1.5">Password</label>
                                    <div class="relative">
                                        <input type="password" name="password" id="user-password" placeholder="Masukkan password" required autocomplete="new-password" class="w-full bg-cream-100 border border-cream-300 focus:border-brand-500/50 rounded-xl px-4 py-2.5 pr-11 text-sm text-slate-800 placeholder:text-slate-400 focus:outline-none transition-colors">
                                        <button type="button" onclick="togglePassword('user-password', this)" class="absolute inset-y-0 right-3 flex items-center text-slate-400 hover:text-slate-600 transition-colors" aria-label="Tampilkan password">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                                
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 mb-1.5">Speed Profile</label>
                                    <select name="profile" id="user-profile" class="w-full bg-cream-100 border border-cream-300 focus:border-brand-500/50 rounded-xl px-4 py-2.5 text-sm text-slate-800 focus:outline-none transition-colors">
                                        <option value="">Tanpa Profile (No Speed Limit)</option>
                                        <?php foreach ($profiles_list as $p): ?>
                                            <option value="<?php echo htmlspecialchars($p['groupname']); ?>"><?php echo htmlspecialchars($p['groupname']); ?> (<?php echo htmlspecialchars($p['rate_limit']); ?>)</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="flex gap-2 pt-2">
                                    <button type="submit" class="flex-grow bg-brand-600 hover:bg-brand-500 text-white font-semibold text-sm py-2.5 px-4 rounded-xl shadow-lg shadow-brand-600/15 transition-all text-center">
                                        Simpan User
                                    </button>
                                    <button type="button" id="user-reset-btn" class="hidden bg-cream-200 hover:bg-cream-300 text-slate-700 border border-cream-300 font-semibold text-sm py-2.5 px-4 rounded-xl transition-all">
                                        Batal
                                    </button>
                                </div>
                            </form>
                        </div>

                    </div>

                    <!-- Column 2 & 3: Data Tables -->
                    <div class="lg:col-span-2 space-y-3">
                        
                        <!-- Table 1: Users List -->
                        <div class="bg-white border border-cream-300 rounded-2xl shadow-xl overflow-hidden">
                            <div class="p-6 border-b border-cream-300 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider">Daftar Pengguna RADIUS</h3>
                                <div class="w-full sm:w-72">
                                    <input id="users-table-search" type="search" oninput="filterTable(this.value, 'users-table')" placeholder="Cari user..." class="w-full bg-cream-100 border border-cream-300 focus:border-brand-500/50 rounded-xl px-4 py-2.5 text-sm text-slate-800 placeholder:text-slate-400 focus:outline-none transition-colors">
                                </div>
                            </div>
                            <div class="relative overflow-x-auto max-h-[700px] overflow-y-auto" id="users-table-wrapper" data-scrollable-table="users-table">
                                <table id="users-table" class="w-full text-left border-collapse">
                                    <thead>
                                        <tr class="bg-cream-100 text-slate-500 text-[10px] uppercase font-bold tracking-wider border-b border-cream-300">
                                            <th class="py-3.5 px-6">Username</th>
                                            <th class="py-3.5 px-6">Password</th>
                                            <th class="py-3.5 px-6">Profile</th>
                                            <th class="py-3.5 px-6">Status</th>
                                            <th class="py-3.5 px-6 text-right">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-cream-200 text-sm">
                                        <?php if (count($users_list) > 0): ?>
                                            <?php foreach ($users_list as $idx => $u): ?>
                                                <tr class="hover:bg-cream-50 transition-colors">
                                                    <td class="py-3 px-6 font-semibold text-slate-700"><?php echo htmlspecialchars($u['username']); ?></td>
                                                    <td class="py-3 px-6 text-slate-500 font-mono text-xs">
                                                        <div class="flex items-center gap-2" data-password-row data-password="<?php echo htmlspecialchars($u['password']); ?>">
                                                            <span class="user-password-text"><?php echo str_repeat('*', strlen($u['password'])); ?></span>
                                                            <button type="button" onclick="togglePasswordRow(this)" data-hidden="true" class="text-slate-400 hover:text-slate-600 transition-colors" aria-label="Tampilkan password">
                                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                                                </svg>
                                                            </button>
                                                        </div>
                                                    </td>
                                                    <td class="py-3 px-6">
                                                        <?php if (!empty($u['profile'])): ?>
                                                            <span class="inline-flex items-center px-2 py-0.5 rounded bg-brand-50 border border-brand-100 text-brand-700 text-xs font-semibold">
                                                                <?php echo htmlspecialchars($u['profile']); ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="text-slate-600 text-xs italic">No Profile</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="py-3 px-6">
                                                        <form method="POST" action="" class="inline">
                                                            <input type="hidden" name="action" value="toggle_user_status">
                                                            <input type="hidden" name="username" value="<?php echo htmlspecialchars($u['username']); ?>">
                                                            <?php if (($u['status'] ?? 'active') === 'disabled'): ?>
                                                                <button type="submit" class="text-[11px] font-semibold px-3 py-1 rounded-full bg-rose-50 text-rose-700 border border-rose-100 hover:bg-rose-100 transition-all">Disabled</button>
                                                            <?php else: ?>
                                                                <button type="submit" class="text-[11px] font-semibold px-3 py-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-100 hover:bg-emerald-100 transition-all">Active</button>
                                                            <?php endif; ?>
                                                        </form>
                                                    </td>
                                                    <td class="py-3 px-6 text-right">
                                                        <div class="flex justify-end gap-2.5">
                                                            <button onclick="editUser('<?php echo miniradius_js_esc($u['username']); ?>', '<?php echo miniradius_js_esc($u['password']); ?>', '<?php echo miniradius_js_esc($u['profile']); ?>')" class="text-xs text-brand-400 hover:text-brand-300 font-semibold px-2 py-1 bg-brand-500/10 border border-brand-500/15 rounded-md hover:bg-brand-500/20 transition-all">
                                                                Edit
                                                            </button>
                                                            <form method="POST" action="" onsubmit="event.preventDefault(); showConfirm(this, 'Apakah Anda yakin ingin menghapus user ini?')">
                                                                <input type="hidden" name="action" value="delete_user">
                                                                <input type="hidden" name="username" value="<?php echo htmlspecialchars($u['username']); ?>">
                                                                <button type="submit" class="text-xs text-rose-400 hover:text-rose-300 font-semibold px-2 py-1 bg-rose-500/10 border border-rose-500/15 rounded-md hover:bg-rose-500/20 transition-all">
                                                                    Hapus
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="4" class="py-8 text-center text-slate-500 font-medium">Belum ada user terdaftar.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                                <div class="scroll-indicator scroll-indicator-down absolute bottom-2 right-4 text-[10px] px-2 py-1 rounded bg-slate-700/80 text-slate-300 pointer-events-none opacity-0 transition-opacity duration-300 flex items-center gap-1">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path></svg>
                                    Scroll
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

            <?php elseif ($page === 'profiles'): ?>
                <!-- =======================================
                     VIEW: PROFILE EDITOR (CRUD FOR PROFILES)
                     ======================================= -->
                <div class="flex flex-col gap-1">
                    <h2 class="text-xl font-bold text-slate-800">Profile Editor</h2>
                    <p class="text-xs text-slate-500">Atur profile pembatasan kecepatan (Rate Limit) untuk Hotspot dan PPPoE</p>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-3">
                    <!-- Column 1: Add/Edit Forms -->
                    <div class="space-y-3">
                        
                        <!-- Form 2: Profile Form -->
                        <div class="bg-white border border-cream-300 rounded-2xl p-6 shadow-xl">
                            <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider mb-4" id="profile-form-title">Tambah Profile Kecepatan</h3>
                            
                            <form method="POST" action="" class="space-y-4">
                                <input type="hidden" name="action" id="profile-action" value="add_profile">
                                <input type="hidden" name="old_groupname" id="profile-old-groupname" value="">
                                
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 mb-1.5">Layanan (Service)</label>
                                    <select name="service" id="profile-service" class="w-full bg-cream-100 border border-cream-300 focus:border-brand-500/50 rounded-xl px-4 py-2.5 text-sm text-slate-800 focus:outline-none transition-colors">
                                        <option value="PPPoE">PPPoE</option>
                                        <option value="Hotspot">Hotspot</option>
                                    </select>
                                </div>
                                
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 mb-1.5">Nama Profile</label>
                                    <input type="text" name="groupname" id="profile-groupname" placeholder="Contoh: 10M, 20M" required class="w-full bg-cream-100 border border-cream-300 focus:border-brand-500/50 rounded-xl px-4 py-2.5 text-sm text-slate-800 placeholder:text-slate-400 focus:outline-none transition-colors">
                                </div>
                                
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 mb-1.5">Speed Limit (Upload/Download)</label>
                                    <input type="text" name="rate_limit" id="profile-rate-limit" placeholder="Format Mikrotik: 2M/10M" required class="w-full bg-cream-100 border border-cream-300 focus:border-brand-500/50 rounded-xl px-4 py-2.5 text-sm text-slate-800 placeholder:text-slate-400 focus:outline-none transition-colors">
                                    <span class="text-[10px] text-slate-500 mt-1 block">Gunakan format Mikrotik Rate Limit: <code>[upload]/[download]</code> (misal: <code>2M/10M</code>)</span>
                                </div>
                                
                                <div class="flex gap-2 pt-2">
                                    <button type="submit" class="flex-grow bg-emerald-600 hover:bg-emerald-500 text-white font-semibold text-sm py-2.5 px-4 rounded-xl shadow-lg shadow-emerald-600/15 transition-all text-center">
                                        Simpan Profile
                                    </button>
                                    <button type="button" id="profile-reset-btn" class="hidden bg-cream-200 hover:bg-cream-300 text-slate-700 border border-cream-300 font-semibold text-sm py-2.5 px-4 rounded-xl transition-all">
                                        Batal
                                    </button>
                                </div>
                            </form>
                        </div>

                    </div>

                    <!-- Column 2 & 3: Data Tables -->
                    <div class="lg:col-span-2 space-y-3">
                        
                        <!-- Table 2: Profiles List -->
                        <div class="bg-white border border-cream-300 rounded-2xl shadow-xl overflow-hidden">
                            <div class="p-6 border-b border-cream-300 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider">Daftar Profile / Rate Limit</h3>
                                <div class="w-full sm:w-72">
                                    <input id="profiles-table-search" type="search" oninput="filterTable(this.value, 'profiles-table')" placeholder="Cari profile..." class="w-full bg-cream-100 border border-cream-300 focus:border-brand-500/50 rounded-xl px-4 py-2.5 text-sm text-slate-800 placeholder:text-slate-400 focus:outline-none transition-colors">
                                </div>
                            </div>
                            <div class="relative overflow-x-auto max-h-[700px] overflow-y-auto" data-scrollable-table="profiles-table">
                                <table id="profiles-table" class="w-full text-left border-collapse">
                                    <thead>
                                        <tr class="bg-cream-100 text-slate-500 text-[10px] uppercase font-bold tracking-wider border-b border-cream-300">
                                            <th class="py-3.5 px-6">Nama Profile</th>
                                            <th class="py-3.5 px-6">Speed Limit (Upload/Download)</th>
                                            <th class="py-3.5 px-6 text-right">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-cream-200 text-sm">
                                        <?php if (count($profiles_list) > 0): ?>
                                            <?php foreach ($profiles_list as $p): ?>
                                                <tr class="hover:bg-cream-50 transition-colors">
                                                    <td class="py-3 px-6 font-semibold text-slate-700"><?php echo htmlspecialchars($p['groupname']); ?></td>
                                                    <td class="py-3 px-6 font-mono text-xs text-emerald-700"><?php echo htmlspecialchars($p['rate_limit']); ?></td>
                                                    <td class="py-3 px-6 text-right">
                                                        <div class="flex justify-end gap-2.5">
                                                            <button onclick="editProfile('<?php echo miniradius_js_esc($p['groupname']); ?>', '<?php echo miniradius_js_esc($p['rate_limit']); ?>')" class="text-xs text-brand-400 hover:text-brand-300 font-semibold px-2 py-1 bg-brand-500/10 border border-brand-500/15 rounded-md hover:bg-brand-500/20 transition-all">
                                                                Edit
                                                            </button>
                                                            <form method="POST" action="" onsubmit="event.preventDefault(); showConfirm(this, 'Apakah Anda yakin ingin menghapus profile ini? Semua user dengan profile ini akan menjadi tanpa profile.')">
                                                                <input type="hidden" name="action" value="delete_profile">
                                                                <input type="hidden" name="groupname" value="<?php echo htmlspecialchars($p['groupname']); ?>">
                                                                <button type="submit" class="text-xs text-rose-400 hover:text-rose-300 font-semibold px-2 py-1 bg-rose-500/10 border border-rose-500/15 rounded-md hover:bg-rose-500/20 transition-all">
                                                                    Hapus
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="3" class="py-8 text-center text-slate-500 font-medium">Belum ada profile terdaftar.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                                <div class="scroll-indicator scroll-indicator-down absolute bottom-2 right-4 text-[10px] px-2 py-1 rounded bg-slate-700/80 text-slate-300 pointer-events-none opacity-0 transition-opacity duration-300 flex items-center gap-1">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path></svg>
                                    Scroll
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

            <?php elseif ($page === 'hotspot' || $page === 'pppoe'): ?>
                <!-- =======================================
                     VIEW: USER HOTSPOT & USER PPPOE TABLES
                     ======================================= -->
                <div class="flex flex-col gap-1">
                    <h2 class="text-xl font-bold text-slate-800"><?php echo $page === 'hotspot' ? 'User Hotspot' : 'User PPPoE'; ?></h2>
                    <p class="text-xs text-slate-500">Menampilkan daftar user terdaftar untuk layanan <?php echo $page === 'hotspot' ? 'Hotspot Portal' : 'PPPoE Tunnel'; ?></p>
                </div>

                <div class="bg-white border border-cream-300 rounded-2xl shadow-xl overflow-hidden">
                    <div class="p-6 border-b border-cream-300 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <span class="text-xs text-slate-500">Di filter berdasarkan Profile Name</span>
                        <div class="w-full sm:w-72">
                            <input id="service-users-table-search" type="search" oninput="filterTable(this.value, 'service-users-table')" placeholder="Cari user layanan..." class="w-full bg-cream-100 border border-cream-300 focus:border-brand-500/50 rounded-xl px-4 py-2.5 text-sm text-slate-800 placeholder:text-slate-400 focus:outline-none transition-colors">
                        </div>
                    </div>
                    
                    <div class="relative overflow-x-auto max-h-[700px] overflow-y-auto" data-scrollable-table="service-users-table">
                        <table id="service-users-table" class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-cream-100 text-slate-500 text-[10px] uppercase font-bold tracking-wider border-b border-cream-300">
                                    <th class="py-3.5 px-6">Username</th>
                                    <th class="py-3.5 px-6">Profile</th>
                                    <th class="py-3.5 px-6">IP Address</th>
                                    <th class="py-3.5 px-6">MAC Address</th>
                                    <th class="py-3.5 px-6">Login Time</th>
                                    <th class="py-3.5 px-6">Total Time</th>
                                    <th class="py-3.5 px-6">Upload</th>
                                    <th class="py-3.5 px-6 text-right">Download</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-cream-200 text-sm">
                                <?php 
                                $filtered_users = [];
                                foreach ($users_list as $u) {
                                    $is_hotspot_u = stripos($u['profile'], 'hotspot') !== false;
                                    $is_pppoe_u = stripos($u['profile'], 'pppoe') !== false;
                                    
                                    if ($page === 'hotspot' && ($is_hotspot_u || empty($u['profile']))) {
                                        $filtered_users[] = $u;
                                    } elseif ($page === 'pppoe' && $is_pppoe_u) {
                                        $filtered_users[] = $u;
                                    }
                                }
                                
                                if (count($filtered_users) > 0):
                                    foreach ($filtered_users as $u):
                                        // Find online status and latest session from logs
                                        $is_online = false;
                                        $user_log = null;
                                        foreach ($logs_list as $l) {
                                            if ($l['username'] === $u['username']) {
                                                if ($l['status'] === 'Online') {
                                                    $is_online = true;
                                                }
                                                $user_log = $l;
                                                break;
                                            }
                                        }
                                ?>
                                    <tr class="hover:bg-cream-50 transition-colors">
                                        <td class="py-3 px-6">
                                            <div class="flex items-center gap-2">
                                                <span class="h-2 w-2 rounded-full <?php echo $is_online ? 'bg-emerald-500 animate-pulse' : 'bg-slate-300'; ?>" title="<?php echo $is_online ? 'Online' : 'Offline'; ?>"></span>
                                                <span class="font-semibold text-slate-700"><?php echo htmlspecialchars($u['username']); ?></span>
                                            </div>
                                        </td>
                                        <td class="py-3 px-6">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded bg-brand-50 border border-brand-100 text-brand-700 text-xs font-semibold">
                                                <?php echo !empty($u['profile']) ? htmlspecialchars($u['profile']) : 'No Profile'; ?>
                                            </span>
                                        </td>
                                        <td class="py-3 px-6 font-mono text-xs text-slate-600">
                                            <?php echo htmlspecialchars($user_log ? $user_log['ip_address'] : '-'); ?>
                                        </td>
                                        <td class="py-3 px-6 font-mono text-xs text-slate-500">
                                            <?php echo htmlspecialchars($user_log ? $user_log['mac_address'] : '-'); ?>
                                        </td>
                                        <td class="py-3 px-6 text-xs text-slate-600">
                                            <?php echo $user_log ? date('d M Y, H:i:s', strtotime($user_log['login_time'])) : '-'; ?>
                                        </td>
                                        <td class="py-3 px-6 font-mono text-xs text-slate-700">
                                            <?php echo $user_log ? miniradius_format_duration($user_log['total_time']) : '0s'; ?>
                                        </td>
                                        <td class="py-3 px-6 font-mono text-xs text-slate-700">
                                            <?php echo $user_log ? miniradius_format_bytes($user_log['upload']) : '0 B'; ?>
                                        </td>
                                        <td class="py-3 px-6 font-mono text-xs text-slate-700 text-right">
                                            <?php echo $user_log ? miniradius_format_bytes($user_log['download']) : '0 B'; ?>
                                        </td>
                                    </tr>
                                <?php 
                                    endforeach;
                                else: 
                                ?>
                                    <tr>
                                        <td colspan="8" class="py-8 text-center text-slate-500 font-medium">Belum ada user untuk kategori ini.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        <div class="scroll-indicator scroll-indicator-down absolute bottom-2 right-4 text-[10px] px-2 py-1 rounded bg-slate-700/80 text-slate-300 pointer-events-none opacity-0 transition-opacity duration-300 flex items-center gap-1">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path></svg>
                            Scroll
                        </div>
                    </div>
                </div>

            <?php elseif ($page === 'settings'): ?>
                <!-- =======================================
                     VIEW: CONNECTION SETTINGS
                     ======================================= -->
                <div class="flex flex-col gap-1">
                    <h2 class="text-xl font-bold text-slate-800">Connection Setting</h2>
                    <p class="text-xs text-slate-500">Atur parameter koneksi database FreeRADIUS dan REST API Router MikroTik L009</p>
                </div>                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    
                    <!-- Form Settings -->
                    <form method="POST" action="" class="space-y-3 md:col-span-2">
                        <input type="hidden" name="action" value="save_settings">
                        
                        <div class="grid grid-cols-1 xl:grid-cols-2 gap-3">
                            <!-- Database Settings -->
                            <div class="bg-white border border-cream-300 rounded-2xl p-6 shadow-xl space-y-4">
                                <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider mb-4 border-b border-cream-300 pb-2">RADIUS DATABASE CONFIG</h3>
                                
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 mb-1.5">DB Host</label>
                                    <input type="text" name="db_host" value="<?php echo htmlspecialchars($config['db_host']); ?>" required class="w-full bg-cream-100 border border-cream-300 focus:border-brand-500/50 rounded-xl px-4 py-2.5 text-sm text-slate-800 placeholder:text-slate-400 focus:outline-none transition-colors">
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 mb-1.5">DB Name</label>
                                    <input type="text" name="db_name" value="<?php echo htmlspecialchars($config['db_name']); ?>" required class="w-full bg-cream-100 border border-cream-300 focus:border-brand-500/50 rounded-xl px-4 py-2.5 text-sm text-slate-800 placeholder:text-slate-400 focus:outline-none transition-colors">
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 mb-1.5">DB User</label>
                                    <input type="text" name="db_user" value="<?php echo htmlspecialchars($config['db_user']); ?>" required class="w-full bg-cream-100 border border-cream-300 focus:border-brand-500/50 rounded-xl px-4 py-2.5 text-sm text-slate-800 placeholder:text-slate-400 focus:outline-none transition-colors">
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 mb-1.5">DB Password</label>
                                    <div class="relative">
                                        <input type="password" name="db_pass" id="db-pass" value="<?php echo htmlspecialchars($config['db_pass']); ?>" class="w-full bg-cream-100 border border-cream-300 focus:border-brand-500/50 rounded-xl px-4 py-2.5 pr-11 text-sm text-slate-800 placeholder:text-slate-400 focus:outline-none transition-colors">
                                        <button type="button" onclick="togglePassword('db-pass', this)" class="absolute inset-y-0 right-3 flex items-center text-slate-400 hover:text-slate-600 transition-colors" aria-label="Tampilkan password">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                            </svg>
                                        </button>
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 mb-1.5">NAS Secret (RADIUS shared secret)</label>
                                    <div class="relative">
                                        <input type="password" name="nas_secret" id="nas-secret" value="<?php echo htmlspecialchars($config['nas_secret']); ?>" class="w-full bg-cream-100 border border-cream-300 focus:border-brand-500/50 rounded-xl px-4 py-2.5 pr-11 text-sm text-slate-800 placeholder:text-slate-400 focus:outline-none transition-colors">
                                        <button type="button" onclick="togglePassword('nas-secret', this)" class="absolute inset-y-0 right-3 flex items-center text-slate-400 hover:text-slate-600 transition-colors" aria-label="Tampilkan password">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                            </svg>
                                        </button>
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 mb-1.5">FreeRADIUS Log File Path</label>
                                    <input type="text" name="radius_log_path" value="<?php echo htmlspecialchars($config['radius_log_path']); ?>" required class="w-full bg-cream-100 border border-cream-300 focus:border-brand-500/50 rounded-xl px-4 py-2.5 text-sm text-slate-800 placeholder:text-slate-400 focus:outline-none transition-colors">
                                </div>
                            </div>

                            <!-- MikroTik Router REST API Settings -->
                            <div class="bg-white border border-cream-300 rounded-2xl p-6 shadow-xl space-y-4">
                                <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider mb-4 border-b border-cream-300 pb-2">MIKROTIK CONFIG (REST API)</h3>
                                
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 mb-1.5">MikroTik Router IP Address</label>
                                    <input type="text" name="mkt_ip" value="<?php echo htmlspecialchars($config['mkt_ip']); ?>" required class="w-full bg-cream-100 border border-cream-300 focus:border-brand-500/50 rounded-xl px-4 py-2.5 text-sm text-slate-800 placeholder:text-slate-400 focus:outline-none transition-colors">
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-500 mb-1.5">Protocol</label>
                                        <select name="mkt_protocol" class="w-full bg-cream-100 border border-cream-300 focus:border-brand-500/50 rounded-xl px-4 py-2.5 text-sm text-slate-800 focus:outline-none transition-colors">
                                            <option value="http" <?php echo $config['mkt_protocol'] === 'http' ? 'selected' : ''; ?>>HTTP</option>
                                            <option value="https" <?php echo $config['mkt_protocol'] === 'https' ? 'selected' : ''; ?>>HTTPS</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-500 mb-1.5">Port API</label>
                                        <input type="text" name="mkt_port" value="<?php echo htmlspecialchars($config['mkt_port']); ?>" required class="w-full bg-cream-100 border border-cream-300 focus:border-brand-500/50 rounded-xl px-4 py-2.5 text-sm text-slate-800 placeholder:text-slate-400 focus:outline-none transition-colors">
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 mb-1.5">API Username</label>
                                    <input type="text" name="mkt_user" value="<?php echo htmlspecialchars($config['mkt_user']); ?>" required class="w-full bg-cream-100 border border-cream-300 focus:border-brand-500/50 rounded-xl px-4 py-2.5 text-sm text-slate-800 placeholder:text-slate-400 focus:outline-none transition-colors">
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 mb-1.5">API Password</label>
                                    <div class="relative">
                                        <input type="password" name="mkt_pass" id="mkt-pass" value="<?php echo htmlspecialchars($config['mkt_pass']); ?>" class="w-full bg-cream-100 border border-cream-300 focus:border-brand-500/50 rounded-xl px-4 py-2.5 pr-11 text-sm text-slate-800 placeholder:text-slate-400 focus:outline-none transition-colors">
                                        <button type="button" onclick="togglePassword('mkt-pass', this)" class="absolute inset-y-0 right-3 flex items-center text-slate-400 hover:text-slate-600 transition-colors" aria-label="Tampilkan password">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                            </svg>
                                        </button>
                                    </div>
                                </div>

                                <div class="hidden">
                                    <input type="hidden" name="nas_ip" value="<?php echo htmlspecialchars($config['nas_ip']); ?>">
                                    <input type="hidden" name="nas_type" value="<?php echo htmlspecialchars($config['nas_type']); ?>">
                                </div>
                            </div>
                        </div>

                        <!-- System Parameters Toggle -->
                        <div class="bg-white border border-cream-300 rounded-2xl p-6 shadow-xl">
                            <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider mb-4 border-b border-cream-300 pb-2">MODUL UTILITIES</h3>
                            <div class="flex items-center justify-between">
                                <div>
                                    <h4 class="text-sm font-bold text-slate-800">Force Mock Mode (Demo Mode)</h4>
                                    <p class="text-xs text-slate-500">Gunakan simulator data jika ingin mencoba interface tanpa koneksi FreeRADIUS.</p>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="mock_mode" id="mock-mode-toggle" <?php echo $config['mock_mode'] ? 'checked' : ''; ?> class="sr-only peer" onchange="toggleMockMode(this)">
                                    <div class="w-11 h-6 bg-cream-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-slate-400 after:border-cream-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-brand-500 peer-checked:after:bg-white"></div>
                                </label>
                                <script>
                                function toggleMockMode(el) {
                                    const checked = el.checked;
                                    fetch('?action=toggle_mock', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'mock_mode=' + (checked ? '1' : '0') })
                                        .then(r => r.json())
                                        .then(data => {
                                            if (data.success) {
                                                window.showNotification(data.message + '. Memuat ulang...', 'success');
                                                setTimeout(() => location.reload(), 800);
                                            } else {
                                                el.checked = !checked;
                                                window.showNotification(data.message || 'Gagal menyimpan', 'error');
                                            }
                                        })
                                        .catch(() => { el.checked = !checked; window.showNotification('Gagal terhubung ke server', 'error'); });
                                }
                                </script>
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit" class="bg-brand-600 hover:bg-brand-500 text-white font-semibold text-sm py-2.5 px-6 rounded-xl shadow-lg shadow-brand-600/15 transition-all text-center">
                                Simpan Semua Konfigurasi
                            </button>
                        </div>
                    </form>
                </div>

            <?php elseif ($page === 'logs'): ?>
                <!-- =======================================
                     VIEW: FREERADIUS LOG VIEWER
                     ======================================= -->
                <div class="flex flex-col gap-1">
                    <h2 class="text-xl font-bold text-slate-800">FreeRADIUS Log Viewer</h2>
                    <p class="text-xs text-slate-500">Real-time viewer untuk file log FreeRADIUS &mdash; <span class="font-mono text-slate-600"><?php echo htmlspecialchars($config['radius_log_path'] ?? '/var/log/freeradius/radius.log'); ?></span></p>
                </div>

                <?php if (!empty($radius_log_error)): ?>
                    <div class="bg-rose-50 border border-rose-200 rounded-2xl p-5 flex items-start gap-3">
                        <svg class="w-5 h-5 text-rose-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <div>
                            <p class="text-sm font-semibold text-rose-700">Tidak dapat membaca file log</p>
                            <p class="text-xs text-rose-600 mt-1"><?php echo $radius_log_error; ?></p>
                            <p class="text-xs text-rose-500 mt-2">Ubah path di menu <a href="?page=settings" class="underline font-semibold">Connection Setting</a>.</p>
                        </div>
                    </div>
                <?php else: ?>

                <!-- Log Viewer Card -->
                <div class="bg-slate-900 border border-slate-700 rounded-2xl shadow-2xl overflow-hidden">
                    <!-- Terminal Header Bar -->
                    <div class="flex items-center justify-between px-5 py-3 bg-slate-800 border-b border-slate-700">
                        <div class="flex items-center gap-3">
                            <!-- Traffic light dots -->
                            <div class="flex items-center gap-1.5">
                                <span class="w-3 h-3 rounded-full bg-red-500 opacity-80"></span>
                                <span class="w-3 h-3 rounded-full bg-amber-400 opacity-80"></span>
                                <span class="w-3 h-3 rounded-full bg-emerald-500 opacity-80"></span>
                            </div>
                            <span class="text-slate-300 text-xs font-mono">radius.log</span>
                            <span id="log-line-count" class="text-[10px] px-2 py-0.5 bg-slate-700 rounded text-slate-400 font-mono"><?php echo count($radius_log_lines); ?> lines</span>
                            <?php if ($is_mock): ?>
                                <span class="text-[10px] px-2 py-0.5 bg-amber-500/20 border border-amber-500/30 rounded text-amber-300 font-mono">DEMO</span>
                            <?php endif; ?>
                        </div>
                        <div class="flex items-center gap-2">
                            <!-- Search Input -->
                            <div class="relative">
                                <svg class="w-3.5 h-3.5 text-slate-500 absolute left-2.5 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                </svg>
                                <input
                                    type="text"
                                    id="log-search"
                                    placeholder="Filter logs..."
                                    class="bg-slate-700 border border-slate-600 text-slate-200 text-xs rounded-lg pl-8 pr-3 py-1.5 w-52 focus:outline-none focus:border-brand-400 placeholder:text-slate-500 font-mono"
                                    oninput="filterLogs(this.value)"
                                >
                            </div>
                            <!-- Scroll to bottom button -->
                            <button onclick="scrollToBottom()" title="Scroll ke bawah" class="p-1.5 rounded-lg bg-slate-700 hover:bg-slate-600 text-slate-400 hover:text-slate-200 transition-colors">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>
                            <!-- Reload button -->
                            <a href="?page=logs" title="Reload log" class="p-1.5 rounded-lg bg-slate-700 hover:bg-slate-600 text-slate-400 hover:text-slate-200 transition-colors">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                </svg>
                            </a>
                        </div>
                    </div>

                    <!-- Log Console -->
                    <div id="log-console" class="overflow-y-auto h-[520px] p-4 font-mono text-xs leading-relaxed">
                        <?php if (count($radius_log_lines) === 0): ?>
                            <p class="text-slate-500 italic">File log kosong atau tidak ada entri.</p>
                        <?php else: ?>
                            <?php foreach ($radius_log_lines as $idx => $line): 
                                $line_escaped = htmlspecialchars($line);
                                // Determine color class based on content
                                $color = 'text-slate-400';
                                $bg = '';
                                $line_lower = strtolower($line);
                                if (preg_match('/login ok|authenticated successfully|Acct-Status-Type = Start/i', $line)) {
                                    $color = 'text-emerald-400';
                                } elseif (preg_match('/login incorrect|invalid user|reject|failed|rlm_chap|attribute not present/i', $line)) {
                                    $color = 'text-rose-400';
                                    $bg = 'bg-rose-500/5';
                                } elseif (preg_match('/error|critical|fatal/i', $line)) {
                                    $color = 'text-red-400';
                                    $bg = 'bg-red-500/8';
                                } elseif (preg_match('/ready to process|listening on|starting|signalled|reload/i', $line)) {
                                    $color = 'text-cyan-400';
                                } elseif (preg_match('/acct|accounting|acct-status/i', $line)) {
                                    $color = 'text-blue-400';
                                } elseif (preg_match('/warning|warn/i', $line)) {
                                    $color = 'text-amber-400';
                                } elseif (preg_match('/info/i', $line)) {
                                    $color = 'text-slate-300';
                                }
                            ?>
                                <div class="log-line flex gap-3 px-1 py-0.5 rounded group <?php echo $bg; ?> hover:bg-white/5 transition-colors" data-text="<?php echo strtolower($line_escaped); ?>">
                                    <span class="text-slate-600 select-none w-8 text-right shrink-0"><?php echo $idx + 1; ?></span>
                                    <span class="<?php echo $color; ?> break-all"><?php echo $line_escaped; ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Footer Status Bar -->
                    <div class="flex items-center justify-between px-5 py-2 bg-slate-800 border-t border-slate-700 text-[10px] font-mono text-slate-500">
                        <div class="flex items-center gap-4">
                            <span class="flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                                <span class="text-emerald-300">Login OK</span>
                            </span>
                            <span class="flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-rose-400"></span>
                                <span class="text-rose-300">Login Fail</span>
                            </span>
                            <span class="flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-red-400"></span>
                                <span class="text-red-300">Error</span>
                            </span>
                            <span class="flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-blue-400"></span>
                                <span class="text-blue-300">Accounting</span>
                            </span>
                            <span class="flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-cyan-400"></span>
                                <span class="text-cyan-300">System</span>
                            </span>
                        </div>
                        <span id="log-visible-count"><?php echo count($radius_log_lines); ?> entries</span>
                    </div>
                </div>



                <?php endif; ?>
            <?php endif; ?>

        </main>
    </div>

    <!-- Footer -->
    <footer class="border-t border-cream-300 bg-cream-200 py-5 text-center text-xs text-slate-500 z-10">
        <p>&copy; <?php echo date('Y'); ?> MiniRadius. Powered by RouterOS L009 REST API & FreeRADIUS 3.2.5.</p>
    </footer>

    <!-- INTERACTIVE SCRIPTS -->


</body>
</html>
