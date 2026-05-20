import 'package:flutter/material.dart';
import 'package:internship/admin/admin_manage_companies_screen.dart';
import 'package:internship/admin/admin_manage_internships_screen.dart';
import 'package:internship/admin/admin_manage_schedules_screen.dart';
import 'package:internship/admin/session_management.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:http/http.dart' as http;
import '../logregis/login.dart';
import 'user_management.dart';

class HomeAdmin extends StatefulWidget {
  const HomeAdmin({Key? key}) : super(key: key);

  @override
  State<HomeAdmin> createState() => _HomeAdminState();
}

class _HomeAdminState extends State<HomeAdmin> {
  // 1. ประกาศตัวแปรไว้รอรับค่า
  String _fullname = "กำลังโหลด...";
  String _username = "";

  @override
  void initState() {
    super.initState();
    // 2. เรียกฟังก์ชันดึงข้อมูลทันทีที่เปิดหน้านี้
    _loadAdminData();
  }

  // ฟังก์ชันดึงข้อมูลจาก SharedPreferences
  Future<void> _loadAdminData() async {
    SharedPreferences prefs = await SharedPreferences.getInstance();
    setState(() {
      // ดึงค่าตาม Key ที่เซฟไว้ในหน้า Login.dart
      _fullname = prefs.getString('currentUser_fullname') ?? "ผู้ดูแลระบบ";
      _username = prefs.getString('currentUser_username') ?? "-";
    });
  }

  Future<void> _logout() async {
    SharedPreferences prefs = await SharedPreferences.getInstance();
    String? username = prefs.getString('currentUser_username');

    if (username != null) {
      try {
        await http.post(
          Uri.parse(
              'https://student.cet.rmutr.ac.th/coopcet/internship/app/logout.php'),
          body: {'users_name': username},
        );
      } catch (e) {
        debugPrint("Logout API Error: $e");
      }
    }

    await prefs.clear();

    if (mounted) {
      Navigator.pushAndRemoveUntil(
        context,
        MaterialPageRoute(builder: (context) => const LoginScreen()),
        (route) => false,
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Admin Dashboard'),
        backgroundColor: Colors.redAccent,
        elevation: 0,
        centerTitle: true,
        actions: [
          IconButton(
            icon: const Icon(Icons.logout),
            onPressed: _showLogoutDialog,
          )
        ],
      ),
      body: Column(
        children: [
          // ส่วนหัว (Header) ที่แสดงชื่อจริงและรหัสผู้ใช้
          Container(
            width: double.infinity,
            padding:
                const EdgeInsets.only(left: 25, right: 25, bottom: 40, top: 10),
            decoration: const BoxDecoration(
              color: Colors.redAccent,
              borderRadius: BorderRadius.only(
                bottomLeft: Radius.circular(40),
                bottomRight: Radius.circular(40),
              ),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text("สวัสดีครับ คุณ",
                    style: TextStyle(color: Colors.white70, fontSize: 16)),
                const SizedBox(height: 5),
                // 3. นำตัวแปรมาแสดงผลตรงนี้
                Text(
                  _fullname,
                  style: const TextStyle(
                      color: Colors.white,
                      fontSize: 26,
                      fontWeight: FontWeight.bold),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                ),
                Text("รหัสแอดมิน: $_username",
                    style:
                        const TextStyle(color: Colors.white60, fontSize: 14)),
              ],
            ),
          ),

          const SizedBox(height: 40),

          Expanded(
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 20),
              child: GridView.count(
                crossAxisCount: 2,
                crossAxisSpacing: 20,
                mainAxisSpacing: 20,
                childAspectRatio: 0.9,
                children: [
                  _buildMenuCard(
                    context,
                    "จัดการสมาชิก",
                    Icons.people_alt_rounded,
                    Colors.orange.shade700,
                    () => Navigator.push(
                        context,
                        MaterialPageRoute(
                            builder: (context) => const UserManagement())),
                  ),
                  _buildMenuCard(
                    context,
                    "จัดการ Session",
                    Icons.phonelink_lock_rounded,
                    Colors.blue.shade700,
                    () {
                      Navigator.push(
                          context,
                          MaterialPageRoute(
                              builder: (context) => const SessionManagement()));
                    },
                  ),
                  _buildMenuCard(
                    context,
                    "จัดการสถานที่ฝึกงาน",
                    Icons.add_business_rounded,
                    Colors.teal.shade700,
                    () {
                      Navigator.push(
                          context,
                          MaterialPageRoute(
                              builder: (context) =>
                                  const AdminManageCompaniesScreen()));
                    },
                  ),
                  _buildMenuCard(
                    context,
                    "จัดการสถานะฝึกงาน",
                    Icons.manage_accounts_rounded,
                    const Color.fromARGB(255, 26, 135, 1),
                    () {
                      Navigator.push(
                          context,
                          MaterialPageRoute(
                              builder: (context) =>
                                  const AdminManageInternshipsScreen()));
                    },
                  ),
                  _buildMenuCard(
                    context,
                    "เพิ่มกำหนดการนิเทศ",
                    Icons.calendar_month_rounded, // เปลี่ยนไอคอนให้ไม่ซ้ำ
                    const Color.fromARGB(255, 35, 195, 201),
                    () {
                      Navigator.push(
                          context,
                          MaterialPageRoute(
                              builder: (context) => AdminManageSchedulesScreen(
                                    adminId:
                                        _username, // 🚨 ลบ const ออกและส่ง _username ไป
                                  )));
                    },
                  ),
                ],
              ),
            ),
          ),
          // 🚨 เรียกใช้งานการ์ดเครดิตตรงนี้ 🚨
          const SizedBox(height: 10), 
          _buildCreditsFooter(), 
          const SizedBox(height: 20),

          const Padding(
            padding: EdgeInsets.only(bottom: 20),
            child: Text("CET Internship System v1.0",
                style: TextStyle(color: Colors.grey)),
          ),
        ],
      ),
    );
  }

  Widget _buildMenuCard(BuildContext context, String title, IconData icon,
      Color color, VoidCallback onTap) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(25),
      child: Container(
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(25),
          boxShadow: [
            BoxShadow(
              color: color.withOpacity(0.2),
              blurRadius: 15,
              offset: const Offset(0, 8),
            ),
          ],
        ),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Container(
              padding: const EdgeInsets.all(15),
              decoration: BoxDecoration(
                color: color.withOpacity(0.1),
                shape: BoxShape.circle,
              ),
              child: Icon(icon, size: 45, color: color),
            ),
            const SizedBox(height: 15),
            Text(
              title,
              style: const TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.bold,
                  color: Colors.black87),
            ),
          ],
        ),
      ),
    );
  }

  void _showLogoutDialog() {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        title: const Text("ออกจากระบบ?"),
        content: const Text("คุณต้องการออกจากระบบใช่หรือไม่?"),
        actions: [
          TextButton(
              onPressed: () => Navigator.pop(context),
              child: const Text("ยกเลิก")),
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
