import 'package:flutter/material.dart';
import 'package:internship/teacher/approval_list_screen.dart';
import 'package:internship/teacher/archive_screen.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:http/http.dart' as http;
import 'dart:convert';

import '../logregis/login.dart';
import 'manage_rounds.dart';
import 'student_request_screen.dart';

class HomeTeacher extends StatefulWidget {
  const HomeTeacher({Key? key}) : super(key: key);

  @override
  State<HomeTeacher> createState() => _HomeTeacherState();
}

class _HomeTeacherState extends State<HomeTeacher> {
  String _fullname = "กำลังโหลด...";
  String _username = "";
  int _pendingRequestsCount = 0;
  int _pendingApprovalCount = 0;
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    // 💡 1. เริ่มต้นด้วยการตรวจสอบ Session ทันทีที่เปิดหน้า
    _verifySession();
  }

  // ==========================================
  // ระบบตรวจสอบ Session แบบ Real-time
  // ==========================================
  Future<void> _verifySession() async {
    SharedPreferences prefs = await SharedPreferences.getInstance();
    String? username = prefs.getString('currentUser_username');
    String? token = prefs.getString('token');

    // ถ้าไม่มีข้อมูลในเครื่อง ให้เตะออกเลย
    if (username == null || token == null) {
      _forceLogout("เซสชันไม่สมบูรณ์ กรุณาเข้าสู่ระบบใหม่");
      return;
    }

    try {
      // ยิงไปถามเซิร์ฟเวอร์ว่า Session ยังอยู่ไหม
      final response = await http.post(
        Uri.parse(
            'https://student.cet.rmutr.ac.th/coopcet/internship/app/check_session.php'),
        body: {
          'users_name': username,
          'token': token,
        },
      ).timeout(const Duration(seconds: 10));

      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        if (data['status'] != 'active') {
          // 🚨 ถ้าโดนลบ Session ในฐานข้อมูล จะโดนเตะออกตรงนี้
          _forceLogout("เซสชันหมดอายุ หรือมีการเข้าสู่ระบบซ้อนที่เครื่องอื่น");
          return;
        }
      }
    } catch (e) {
      debugPrint("Verify Session Network Error: $e");
      // ถ้าแค่เน็ตหลุด ให้ปล่อยผ่านไปโหลดข้อมูลปกติ
    }

    // ถ้าผ่านการเช็ค (หรือข้ามเพราะเน็ตพัง) ให้เริ่มโหลดข้อมูลหน้าจอ
    if (mounted) _loadTeacherData();
  }

  // ฟังก์ชันเตะผู้ใช้ออกพร้อมแจ้งเตือน
  void _forceLogout(String msg) async {
    SharedPreferences prefs = await SharedPreferences.getInstance();
    await prefs.clear();

    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(msg), backgroundColor: Colors.red));
      Navigator.pushAndRemoveUntil(
          context,
          MaterialPageRoute(builder: (context) => const LoginScreen()),
          (route) => false);
    }
  }

  // ==========================================
  // โหลดข้อมูล Dashboard ของอาจารย์
  // ==========================================
  Future<void> _loadTeacherData() async {
    if (!mounted) return;
    setState(() => _isLoading = true);

    SharedPreferences prefs = await SharedPreferences.getInstance();
    if (mounted) {
      setState(() {
        _fullname = prefs.getString('currentUser_fullname') ?? "อาจารย์ผู้ดูแล";
        _username = prefs.getString('currentUser_username') ?? "-";
      });
    }

    // เรียกโหลดจำนวนแจ้งเตือนทั้ง 2 ตัว
    await _fetchAllCounts();

    // โหลดเสร็จแล้วปิด Loading
    if (mounted) setState(() => _isLoading = false);
  }

  Future<void> _fetchAllCounts() async {
    await Future.wait([
      _fetchPendingRequestsCount(),
      _fetchPendingApprovalCount(),
    ]);
  }

  Future<void> _fetchPendingRequestsCount() async {
    try {
      final response = await http
          .get(
            Uri.parse(
                'https://student.cet.rmutr.ac.th/coopcet/internship/app/get_relocating_requests.php'),
          )
          .timeout(const Duration(seconds: 10)); // 🚨 ใส่ Timeout กันค้าง

      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        if (data['success'] && mounted) {
          setState(() {
            _pendingRequestsCount = (data['data'] as List).length;
          });
        }
      }
    } catch (e) {
      debugPrint("Fetch requests count error: $e");
    }
  }

  Future<void> _fetchPendingApprovalCount() async {
    try {
      final response = await http
          .get(
            Uri.parse(
                'https://student.cet.rmutr.ac.th/coopcet/internship/app/get_pending_approvals_count.php'),
          )
          .timeout(const Duration(seconds: 10)); // 🚨 ใส่ Timeout กันค้าง

      final data = jsonDecode(response.body);
      if (data['success'] && mounted) {
        setState(() {
          _pendingApprovalCount = data['count'];
        });
      }
    } catch (e) {
      debugPrint("Error fetching pending approvals count: $e");
    }
  }

  Future<void> _logout() async {
    SharedPreferences prefs = await SharedPreferences.getInstance();
    String? username = prefs.getString('currentUser_username');

    // ล้างข้อมูลในเครื่องก่อนเลย
    await prefs.clear();

    if (username != null) {
      try {
        await http.post(
          Uri.parse(
              'https://student.cet.rmutr.ac.th/coopcet/internship/app/logout.php'),
          body: {'users_name': username},
        ).timeout(const Duration(seconds: 5)); // 🚨 รอแค่ 5 วิพอ
      } catch (e) {
        debugPrint("Logout API Error (Ignored)");
      }
    }

    if (mounted) {
      Navigator.pushAndRemoveUntil(
        context,
        MaterialPageRoute(builder: (context) => const LoginScreen()),
        (route) => false,
      );
    }
  }

  void _showLogoutDialog() {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        title: const Row(
          children: [
            Icon(Icons.logout, color: Colors.redAccent),
            SizedBox(width: 10),
            Text("ออกจากระบบ?"),
          ],
        ),
        content: const Text("คุณต้องการออกจากระบบใช่หรือไม่?"),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text("ยกเลิก", style: TextStyle(color: Colors.grey)),
          ),
          ElevatedButton(
            onPressed: _logout,
            style: ElevatedButton.styleFrom(
                backgroundColor: Colors.redAccent,
                shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(10))),
            child: const Text("ตกลง", style: TextStyle(color: Colors.white)),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final Color primaryColor = Colors.teal.shade700;

    return Scaffold(
      backgroundColor: Colors.grey.shade50,
      appBar: AppBar(
        title: const Text('Teacher Dashboard',
            style: TextStyle(fontWeight: FontWeight.bold)),
        backgroundColor: primaryColor,
        foregroundColor: Colors.white,
        elevation: 0,
        centerTitle: true,
        actions: [
          IconButton(
            icon: const Icon(Icons.power_settings_new_rounded),
            onPressed: _showLogoutDialog,
            tooltip: 'ออกจากระบบ',
          )
        ],
      ),
      body: _isLoading
          ? Center(child: CircularProgressIndicator(color: primaryColor))
          : RefreshIndicator(
              onRefresh: _loadTeacherData,
              color: primaryColor,
              child: SingleChildScrollView(
                physics: const AlwaysScrollableScrollPhysics(),
                child: Column(
                  children: [
                    Container(
                      width: double.infinity,
                      padding: const EdgeInsets.only(
                          left: 30, right: 30, bottom: 40, top: 10),
                      decoration: BoxDecoration(
                          color: primaryColor,
                          borderRadius: const BorderRadius.only(
                            bottomLeft: Radius.circular(40),
                            bottomRight: Radius.circular(40),
                          ),
                          boxShadow: [
                            BoxShadow(
                                color: primaryColor.withOpacity(0.3),
                                blurRadius: 10,
                                offset: const Offset(0, 5))
                          ]),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text("สวัสดีครับ อาจารย์",
                              style: TextStyle(
                                  color: Colors.white70, fontSize: 16)),
                          const SizedBox(height: 5),
                          Text(
                            _fullname,
                            style: const TextStyle(
                                color: Colors.white,
                                fontSize: 26,
                                fontWeight: FontWeight.bold),
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                          ),
                          const SizedBox(height: 5),
                          Container(
                            padding: const EdgeInsets.symmetric(
                                horizontal: 10, vertical: 4),
                            decoration: BoxDecoration(
                                color: Colors.white.withOpacity(0.2),
                                borderRadius: BorderRadius.circular(20)),
                            child: Text("รหัสประจำตัว: $_username",
                                style: const TextStyle(
                                    color: Colors.white, fontSize: 13)),
                          ),
                        ],
                      ),
                    ),

                    const SizedBox(height: 30),

                    Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 20),
                      child: GridView.count(
                        shrinkWrap: true,
                        crossAxisCount: 2,
                        crossAxisSpacing: 20,
                        mainAxisSpacing: 20,
                        childAspectRatio: 0.95,
                        physics: const NeverScrollableScrollPhysics(),
                        children: [
                          // เมนูที่ 1: จัดการรอบการฝึกงาน
                          _buildMenuCard(
                            context,
                            "จัดการรอบฝึกงาน",
                            Icons.date_range_rounded,
                            Colors.teal.shade600,
                            () {
                              Navigator.push(
                                  context,
                                  MaterialPageRoute(
                                      builder: (context) =>
                                          const ManageRounds()));
                            },
                          ),

                          // เมนูที่ 2: คำขอย้ายที่ฝึก (มี Badge แจ้งเตือน)
                          _buildMenuCard(
                            context,
                            "คำขอย้ายสถานที่",
                            Icons.assignment_ind_rounded,
                            Colors.orange.shade600,
                            () async {
                              await Navigator.push(
                                  context,
                                  MaterialPageRoute(
                                      builder: (context) =>
                                          const StudentRequestScreen()));
                              _fetchPendingRequestsCount();
                            },
                            badgeCount: _pendingRequestsCount,
                          ),

                          // เมนูที่ 3: ตรวจสอบคำขอจบฝึกงาน
                          _buildMenuCard(
                            context,
                            "คำขอจบฝึกงาน",
                            Icons.how_to_reg_rounded,
                            Colors.orange.shade600,
                            () async {
                              await Navigator.push(
                                  context,
                                  MaterialPageRoute(
                                      builder: (context) =>
                                          const ApprovalListScreen()));
                              _fetchPendingApprovalCount();
                            },
                            badgeCount: _pendingApprovalCount,
                          ),

                          // เมนูที่ 4: ตรวจสอบบันทึกการทำงาน
                          _buildMenuCard(
                            context,
                            "คลังข้อมูล (ฝึกจบแล้ว)",
                            Icons.menu_book_rounded,
                            Colors.blue.shade600,
                            () {
                              Navigator.push(
                                  context,
                                  MaterialPageRoute(
                                      builder: (context) =>
                                          const ArchiveScreen()));
                            },
                          ),
                        ],
                      ),
                    ),
                    // 🚨 เรียกใช้งานการ์ดเครดิตตรงนี้ 🚨
                    const SizedBox(height: 30),
                    _buildCreditsFooter(),
                    const SizedBox(height: 1),

                    // --- Footer ---
                    const Padding(
                      padding: EdgeInsets.only(bottom: 25, top: 30),
                      child: Text("CET Internship System v1.0",
                          style: TextStyle(color: Colors.grey, fontSize: 12)),
                    ),
                  ],
                ),
              ),
            ),
    );
  }

  Widget _buildMenuCard(BuildContext context, String title, IconData icon,
      Color color, VoidCallback onTap,
      {int badgeCount = 0}) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(25),
      splashColor: color.withOpacity(0.1),
      highlightColor: color.withOpacity(0.05),
      child: Stack(
        clipBehavior: Clip.none,
        children: [
          Container(
            width: double.infinity,
            height: double.infinity,
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(25),
              boxShadow: [
                BoxShadow(
                    color: color.withOpacity(0.15),
                    blurRadius: 15,
                    offset: const Offset(0, 8)),
              ],
              border: Border.all(color: color.withOpacity(0.05), width: 1),
            ),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Container(
                  padding: const EdgeInsets.all(18),
                  decoration: BoxDecoration(
                    color: color.withOpacity(0.1),
                    shape: BoxShape.circle,
                  ),
                  child: Icon(icon, size: 40, color: color),
                ),
                const SizedBox(height: 15),
                Text(
                  title,
                  style: TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.bold,
                      color: Colors.grey.shade800),
                  textAlign: TextAlign.center,
                ),
              ],
            ),
          ),
          if (badgeCount > 0)
            Positioned(
              top: -5,
              right: -5,
              child: Container(
                padding: const EdgeInsets.all(8),
                decoration: const BoxDecoration(
                  color: Colors.red,
                  shape: BoxShape.circle,
                ),
                child: Text(
                  badgeCount.toString(),
                  style: const TextStyle(
                      color: Colors.white,
                      fontSize: 14,
                      fontWeight: FontWeight.bold),
                ),
              ),
            ),
        ],
      ),
    );
  }

  // ==========================================
  // ส่วนแสดงเครดิตผู้จัดทำ (Credits Footer)
  // ==========================================
  Widget _buildCreditsFooter() {
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 20),
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(15),
        border: Border.all(color: Colors.grey.shade200),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.02),
            blurRadius: 10,
            spreadRadius: 2,
          )
        ],
      ),
      child: Column(
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(Icons.code_rounded,
                  color: Colors.blueGrey.shade400, size: 20),
              const SizedBox(width: 8),
              Text(
                "ทีมผู้จัดทำระบบคลังสหกิจ",
                style: TextStyle(
                  fontWeight: FontWeight.bold,
                  color: Colors.blueGrey.shade700,
                  fontSize: 14,
                ),
              ),
            ],
          ),
          const Padding(
            padding: EdgeInsets.symmetric(vertical: 10),
            child: Divider(height: 1, thickness: 1),
          ),
          _buildCreatorRow("2651031741134", "นายธนกฤต ดีล้วน"),
          const SizedBox(height: 8),
          _buildCreatorRow("2651031741145", "นายศิรศักดิ์ ดินแดง"),
        ],
      ),
    );
  }

  Widget _buildCreatorRow(String studentId, String name) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(
          studentId,
          style: TextStyle(
            color: Colors.grey.shade500,
            fontSize: 13,
            letterSpacing: 0.5,
          ),
        ),
        Text(
          name,
          style: TextStyle(
            color: Colors.blueGrey.shade800,
            fontSize: 13,
            fontWeight: FontWeight.w600,
          ),
        ),
      ],
    );
  }
}
