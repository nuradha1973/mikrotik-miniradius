<?php
return [
    'profiles' => [
        'PPPoE_10M' => '2M/10M',
        'PPPoE_20M' => '5M/20M',
        'Hotspot_5M' => '1M/5M',
        'Hotspot_10M' => '2M/10M',
    ],
    'users' => [
        ['username' => 'budi.santoso', 'password' => 'password123', 'profile' => 'PPPoE_10M', 'status' => 'active'],
        ['username' => 'ani.lestari', 'password' => 'hotspotpass', 'profile' => 'Hotspot_5M', 'status' => 'active'],
        ['username' => 'joko.susilo', 'password' => 'pppoepass', 'profile' => 'PPPoE_20M', 'status' => 'disabled'],
        ['username' => 'mega.wati', 'password' => 'pass999', 'profile' => 'Hotspot_10M', 'status' => 'active'],
        ['username' => 'rudi.permadi', 'password' => 'rudi888', 'profile' => 'PPPoE_10M', 'status' => 'active'],
        ['username' => 'siti.aminah', 'password' => 'siti123', 'profile' => 'Hotspot_5M', 'status' => 'disabled'],
    ],
    'logs' => [
        ['username' => 'budi.santoso', 'ip_address' => '10.10.20.15', 'mac_address' => '00:1A:2B:3C:4D:5E', 'login_time' => date('Y-m-d H:i:s', time() - 3630), 'status' => 'Online', 'protocol' => 'PPPoE', 'total_time' => 3630, 'upload' => 12450000, 'download' => 84500000],
        ['username' => 'ani.lestari', 'ip_address' => '10.10.20.22', 'mac_address' => '24:FD:52:11:AB:CC', 'login_time' => date('Y-m-d H:i:s', time() - 120), 'status' => 'Online', 'protocol' => 'Hotspot', 'total_time' => 120, 'upload' => 450000, 'download' => 3200000],
        ['username' => 'joko.susilo', 'ip_address' => '192.168.88.254', 'mac_address' => '3C:D0:F8:77:E1:92', 'login_time' => date('Y-m-d H:i:s', time() - 7200), 'status' => 'Online', 'protocol' => 'PPPoE', 'total_time' => 7200, 'upload' => 24500000, 'download' => 156000000],
        ['username' => 'mega.wati', 'ip_address' => '10.10.20.40', 'mac_address' => 'B0:C5:54:E3:42:01', 'login_time' => date('Y-m-d H:i:s', time() - 500), 'status' => 'Offline', 'protocol' => 'Hotspot', 'total_time' => 450, 'upload' => 1200000, 'download' => 8900000],
        ['username' => 'rudi.permadi', 'ip_address' => '192.168.88.241', 'mac_address' => 'FC:AA:14:88:99:FF', 'login_time' => date('Y-m-d H:i:s', time() - 18000), 'status' => 'Online', 'protocol' => 'PPPoE', 'total_time' => 18000, 'upload' => 45000000, 'download' => 312000000],
        ['username' => 'siti.aminah', 'ip_address' => '10.10.20.55', 'mac_address' => 'A4:C3:F0:22:11:33', 'login_time' => date('Y-m-d H:i:s', time() - 1400), 'status' => 'Offline', 'protocol' => 'Hotspot', 'total_time' => 1100, 'upload' => 2300000, 'download' => 14500000],
    ],
];
?>
