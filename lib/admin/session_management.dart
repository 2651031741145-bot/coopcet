import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'dart:convert';

class SessionManagement extends StatefulWidget {
  const SessionManagement({Key? key}) : super(key: key);

  @override
  State<SessionManagement> createState() => _SessionManagementState();
}

class _SessionManagementState extends State<SessionManagement> {
  List<dynamic> _activeSessions = [];
  bool _isLoading = true;

  // ตัวแปรสำหรับระบบค้นหา
  bool _isSearching = false;
  final TextEditingController _searchController = TextEditingController();
  String _searchQuery = "";

  @override
  void initState() {
    super.initState();
    _fetchSessions();

    // ฟังการเปลี่ยนแปลงในช่องค้นหา
    _searchController.addListener(() {
      setState(() {
        _searchQuery = _searchController.text;
      });
    });
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  // ดึงข้อมูล Session
  Future<void> _fetchSessions() async {
    setState(() => _isLoading = true);
    try {
      final response = await http.get(
        Uri.parse('https://student.cet.rmutr.ac.th/coopcet/internship/app/get_active_sessions.php'),
      );
      if (response.statusCode == 200) {
        setState(() {
          _activeSessions = jsonDecode(response.body);
          _isLoading = false;
        });
      }
    } catch (e) {
      debugPrint("Fetch Sessions Error: $e");
      setState(() => _isLoading = false);
    }
  }

  // ฟังก์ชันยิง API ล้าง Session
  Future<void> _clearSession({required String target, String? username}) async {
    try {
      final response = await http.post(
        Uri.parse('https://student.cet.rmutr.ac.th/coopcet/internship/app/clear_session.php'),
        body: {
          'target': target,
          'users_name': username ?? '',
        },
      );
      final data = jsonDecode(response.body);
      if (data['success'] == true) {
        _fetchSessions(); 
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(target == 'all' 
                ? 'ล้างระบบการเข้าสู่ระบบทั้งหมดเรียบร้อยแล้ว' 
                : 'ล้างสถานะของ $username เรียบร้อยแล้ว'),
              backgroundColor: Colors.green,
            ),
          );
        }
      }
    } catch (e) {
      debugPrint("Clear Session Error: $e");
    }
  }

  // กรองข้อมูลตาม Tab และช่องค้นหา
  List<dynamic> _filterSessions(String level) {
    List<dynamic> filtered = _activeSessions.where((user) => user['user_level'] == level).toList();
    
    if (_searchQuery.isNotEmpty) {
      filtered = filtered.where((user) {
        final fullName = user['full_name'].toString().toLowerCase();
        final userName = user['users_name'].toString().toLowerCase();
        final query = _searchQuery.toLowerCase();
        
        return fullName.contains(query) || userName.contains(query);
      }).toList();
    }
    return filtered;
  }

  @override
  Widget build(BuildContext context) {
    final primaryColor = const Color(0xFF37474F); // สี BlueGrey เข้ม

    return DefaultTabController(
      length: 3,
      child: Scaffold(
        appBar: AppBar(
          title: _isSearching 
            ? TextField(
                controller: _searchController,
                autofocus: true,
                decoration: const InputDecoration(
                  hintText: 'ค้นหาชื่อ หรือ ID...',
                  border: InputBorder.none,
                  hintStyle: TextStyle(color: Colors.white70),
                ),
                style: const TextStyle(color: Colors.white, fontSize: 18),
              )
            : const Text('จัดการ Session'),
          backgroundColor: primaryColor,
          foregroundColor: Colors.white,
          elevation: 0,
          actions: [
            // ปุ่มแว่นขยายสำหรับค้นหา
            IconButton(
              icon: Icon(_isSearching ? Icons.close : Icons.search),
              onPressed: () {
                setState(() {
                  _isSearching = !_isSearching;
                  if (!_isSearching) {
                    _searchController.clear();
                    _searchQuery = "";
                  }
                });
              },
            ),
            // ปุ่มล้างทั้งหมดที่มุมขวาบน (แบบมีข้อความ)
            TextButton.icon(
              onPressed: () => _confirmClear(target: 'all'),
              icon: const Icon(Icons.delete_sweep, color: Colors.orangeAccent),
              label: const Text(
                'ล้างทั้งหมด',
                style: TextStyle(color: Colors.orangeAccent, fontWeight: FontWeight.bold),
              ),
            ),
            const SizedBox(width: 8),
          ],
          bottom: const TabBar(
            labelColor: Colors.white,
            unselectedLabelColor: Colors.white70,
            indicatorColor: Colors.white,
            tabs: [
              Tab(icon: Icon(Icons.admin_panel_settings), text: "แอดมิน"),
              Tab(icon: Icon(Icons.school), text: "อาจารย์"),
              Tab(icon: Icon(Icons.person), text: "นักศึกษา"),
            ],
          ),
        ),
        body: Column(
          children: [
            // --- ส่วนข้อความกำกับสถานะ (อยู่เหนือ Tab เสมอ) ---
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(20),
              color: Colors.grey[100],
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Row(
                    children: [
                      Icon(Icons.info_outline, size: 20, color: Colors.blueGrey),
                      SizedBox(width: 8),
                      Text(
                        "บัญชีที่มีการเข้าสู่ระบบค้างไว้",
                        style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: Colors.black87),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  Text(
                    "รายการสมาชิกที่ยังไม่ได้ออกจากระบบจากอุปกรณ์อื่น ท่านสามารถกดปุ่มล้าง (ไอคอนสีแดง) เพื่อคืนสิทธิ์ให้สมาชิกสามารถเข้าสู่ระบบจากเครื่องใหม่ได้ทันที",
                    style: TextStyle(fontSize: 13, color: Colors.grey[700], height: 1.4),
                  ),
                ],
              ),
            ),
            const Divider(height: 1),

            // --- ส่วนแสดงรายชื่อแบบแยก Tab ---
            Expanded(
              child: _isLoading
                  ? const Center(child: CircularProgressIndicator())
                  : TabBarView(
                      children: [
                        _buildSessionList(_filterSessions('a')), // แอดมิน
                        _buildSessionList(_filterSessions('t')), // อาจารย์
                        _buildSessionList(_filterSessions('s')), // นักศึกษา
                      ],
                    ),
            ),
          ],
        ),
      ),
    );
  }

  // Widget สร้าง List ในแต่ละแท็บ พร้อมรองรับ RefreshIndicator
  Widget _buildSessionList(List<dynamic> sessions) {
    return RefreshIndicator(
      onRefresh: _fetchSessions,
      child: sessions.isEmpty
          ? ListView( // ใช้ ListView เพื่อให้ยังสามารถดึงเพื่อ Refresh ได้
              children: [
                SizedBox(height: MediaQuery.of(context).size.height * 0.2),
                Icon(_searchQuery.isEmpty ? Icons.phonelink_off : Icons.search_off, size: 80, color: Colors.grey[300]),
                const SizedBox(height: 16),
                Center(
                  child: Text(
                    _searchQuery.isEmpty ? "ไม่มีบัญชีที่ค้างในระบบ" : "ไม่พบข้อมูลที่ค้นหา",
                    style: const TextStyle(color: Colors.grey, fontSize: 16),
                  ),
                ),
              ],
            )
          : ListView.builder(
              padding: const EdgeInsets.all(10),
              itemCount: sessions.length,
              itemBuilder: (context, index) {
                final user = sessions[index];
                return Card(
                  elevation: 2,
                  margin: const EdgeInsets.only(bottom: 10),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  child: ListTile(
                    contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                    leading: Container(
                      padding: const EdgeInsets.all(10),
                      decoration: BoxDecoration(
                        color: Colors.green.withOpacity(0.1),
                        shape: BoxShape.circle,
                      ),
                      child: const Icon(Icons.devices, color: Colors.green),
                    ),
                    title: Text(
                      user['full_name'], 
                      style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16)
                    ),
                    subtitle: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const SizedBox(height: 4),
                        Text("ID: ${user['users_name']}"),
                      ],
                    ),
                    trailing: IconButton(
                      icon: const Icon(Icons.link_off, color: Colors.redAccent, size: 28),
                      onPressed: () => _confirmClear(target: 'single', username: user['users_name']),
                      tooltip: 'ปลดล็อกบัญชี',
                    ),
                  ),
                );
              },
            ),
    );
  }

  // แจ้งเตือนยืนยันการล้างข้อมูล
  void _confirmClear({required String target, String? username}) {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        title: Row(
          children: [
            Icon(Icons.warning_amber_rounded, color: target == 'all' ? Colors.red : Colors.orange),
            const SizedBox(width: 10),
            Text(target == 'all' ? "ล้างข้อมูลทั้งหมด?" : "ปลดล็อกบัญชี?"),
          ],
        ),
        content: Text(target == 'all' 
          ? "สมาชิกทุกคนที่ใช้งานอยู่จะถูกบังคับให้ออกจากระบบทันที ยืนยันการดำเนินการหรือไม่?"
          : "ต้องการล้างสถานะการเข้าสู่ระบบของคุณ $username ใช่หรือไม่?"),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context), child: const Text("ยกเลิก")),
          ElevatedButton(
            onPressed: () {
              Navigator.pop(context);
              _clearSession(target: target, username: username);
            },
            style: ElevatedButton.styleFrom(
              backgroundColor: target == 'all' ? Colors.red : Colors.orange[800],
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
            ),
            child: const Text("ยืนยัน", style: TextStyle(color: Colors.white)),
          ),
        ],
      ),
    );
  }
}