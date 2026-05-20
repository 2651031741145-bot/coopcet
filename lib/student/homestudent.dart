import 'package:flutter/material.dart';
import 'package:internship/student/student_archive_screen.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:http/http.dart' as http;
import 'dart:convert';
import 'package:url_launcher/url_launcher.dart';

import '../logregis/login.dart';
import 'search_company.dart';
import 'daily_log_screen.dart';
import 'relocate_screen.dart';

class HomeStudent extends StatefulWidget {
  const HomeStudent({Key? key}) : super(key: key);

  @override
  State<HomeStudent> createState() => _HomeStudentState();
}

class _HomeStudentState extends State<HomeStudent> {
  String _fullname = "กำลังโหลด...";
  String _username = "";
  Map<String, dynamic>? _activeInternship;

  bool _isLoading = true;
  bool _hasError = false;
  bool _isReadOnly = true;

  @override
  void initState() {
    super.initState();
    // 💡 เริ่มต้นด้วยการตรวจสอบ Session ทันทีที่เปิดหน้า
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
      // แอบยิงไปถามเซิร์ฟเวอร์ว่า Session ยังอยู่ไหม
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
      // ถ้าแค่เน็ตหลุด ให้ปล่อยผ่านไปโหลดข้อมูลปกติ (จะได้ขึ้นหน้า Error แดงๆ ให้กดรีเฟรชได้)
    }

    // ถ้าผ่านการเช็ค (หรือข้ามเพราะเน็ตพัง) ให้เริ่มโหลดข้อมูลหน้าจอ
    if (mounted) _loadInitialData();
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
  // โหลดข้อมูล Dashboard
  // ==========================================
  Future<void> _loadInitialData() async {
    if (!mounted) return;
    setState(() {
      _isLoading = true;
      _hasError = false;
    });

    SharedPreferences prefs = await SharedPreferences.getInstance();
    if (mounted) {
      setState(() {
        _fullname = prefs.getString('currentUser_fullname') ?? "นักศึกษา";
        _username = prefs.getString('currentUser_username') ?? "-";
      });
    }

    try {
      await Future.wait([
        _fetchActiveInternship(),
        _checkAccessStatus(),
      ]);
    } catch (e) {
      debugPrint("Load Data Error: $e");
      if (mounted) {
        setState(() => _hasError = true);
      }
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  Future<void> _fetchActiveInternship() async {
    final response = await http
        .get(
          Uri.parse(
              'https://student.cet.rmutr.ac.th/coopcet/internship/app/get_active_internship.php?student_id=$_username'),
        )
        .timeout(const Duration(seconds: 10));

    if (response.statusCode == 200) {
      final data = jsonDecode(response.body);
      if (mounted) {
        setState(() {
          _activeInternship = data['success'] == true ? data['data'] : null;
        });
      }
    } else {
      throw Exception("Server Error");
    }
  }

  Future<void> _checkAccessStatus() async {
    final response = await http
        .get(
          Uri.parse(
              'https://student.cet.rmutr.ac.th/coopcet/internship/app/check_student_access.php?student_id=$_username'),
        )
        .timeout(const Duration(seconds: 10));

    if (response.statusCode == 200) {
      final data = jsonDecode(response.body);
      if (mounted) {
        setState(() {
          _isReadOnly = data['is_readonly'] ?? true;
        });
      }
    } else {
      throw Exception("Server Error");
    }
  }

  Future<void> _navigateToLocation(String lat, String lng) async {
    final String googleMapsUrl =
        "https://www.google.com/maps/search/?api=1&query=$lat,$lng";
    final Uri uri = Uri.parse(googleMapsUrl);
    if (await canLaunchUrl(uri)) {
      await launchUrl(uri, mode: LaunchMode.externalApplication);
    } else {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('ไม่สามารถเปิด Google Maps ได้')));
      }
    }
  }

  Future<void> _submitInternshipRequest(
      String companyId, String companyName) async {
    showDialog(
        context: context,
        barrierDismissible: false,
        builder: (context) => const Center(child: CircularProgressIndicator()));
    try {
      final response = await http.post(
        Uri.parse(
            'https://student.cet.rmutr.ac.th/coopcet/internship/app/submit_request.php'),
        body: {'student_id': _username, 'company_id': companyId},
      ).timeout(const Duration(seconds: 10));

      if (!mounted) return;
      Navigator.pop(context);

      final data = jsonDecode(response.body);
      if (data['success']) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(
            content: Text(data['message']), backgroundColor: Colors.green));
        _loadInitialData();
      } else {
        _showError(data['message']);
      }
    } catch (e) {
      if (!mounted) return;
      Navigator.pop(context);
      _showError("เกิดข้อผิดพลาดในการเชื่อมต่อ หรือใช้เวลานานเกินไป");
    }
  }

  void _confirmRequest(Map<String, dynamic> company) {
    // 🚨 ท่าไม้ตาย: ใช้ Regex ตัดคำว่า (ID: ตัวเลข) ออกจากชื่อบริษัทให้เหลือแต่ชื่อเน้นๆ
    String cleanCompanyName =
        company['name'].toString().replaceAll(RegExp(r'\s*\(ID:\s*\d+\)'), '');

    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(15)),
        title: const Row(
          children: [
            Icon(Icons.business_center, color: Colors.blue),
            SizedBox(width: 10),
            Text("เริ่มการฝึกงาน?",
                style:
                    TextStyle(fontWeight: FontWeight.bold, color: Colors.blue)),
          ],
        ),
        content: Text(
            "ยืนยันการเลือกฝึกงานที่\n\n'$cleanCompanyName'\n\nใช่หรือไม่?",
            textAlign: TextAlign.center, // จัดให้อยู่กึ่งกลางจะได้ดูสวยขึ้น
            style: const TextStyle(fontSize: 15)),
        actions: [
          TextButton(
              onPressed: () => Navigator.pop(context),
              child: const Text("ยกเลิก",
                  style: TextStyle(
                      color: Colors.grey, fontWeight: FontWeight.bold))),
          ElevatedButton(
            onPressed: () {
              Navigator.pop(context);
              // 🚨 ส่งชื่อบริษัทที่ "ทำความสะอาดแล้ว" ไปบันทึก
              _submitInternshipRequest(
                  company['id'].toString(), cleanCompanyName);
            },
            style: ElevatedButton.styleFrom(
                backgroundColor: Colors.blue.shade700,
                shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(8))),
            child: const Text("ยืนยัน",
                style: TextStyle(
                    color: Colors.white, fontWeight: FontWeight.bold)),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final primaryColor = _isReadOnly ? Colors.blueGrey : Colors.blue.shade700;

    return Scaffold(
      backgroundColor: Colors.grey[50],
      appBar: AppBar(
        title: Text(
            _isReadOnly ? 'Student Dashboard (View Only)' : 'Student Dashboard',
            style: const TextStyle(fontWeight: FontWeight.bold)),
        backgroundColor: primaryColor,
        foregroundColor: Colors.white,
        elevation: 0,
        centerTitle: true,
        actions: [
          IconButton(
              icon: const Icon(Icons.logout),
              onPressed: _showLogoutDialog,
              tooltip: 'ออกจากระบบ')
        ],
      ),
      body: RefreshIndicator(
        onRefresh: _loadInitialData,
        child: SingleChildScrollView(
          physics: const AlwaysScrollableScrollPhysics(),
          child: Column(
            children: [
              _buildHeader(primaryColor),
              const SizedBox(height: 20),
              if (_isReadOnly && !_hasError && !_isLoading)
                _buildReadOnlyWarning(),
              _buildInternshipCard(),
              const SizedBox(height: 20),
              _buildMenuGrid(),
              const SizedBox(height: 40),
              // 🚨 เรียกใช้การ์ดผู้จัดทำตรงนี้ 🚨
              _buildCreditsFooter(),
              const SizedBox(height: 1),
              const Padding(
                padding: EdgeInsets.only(bottom: 25, top: 30),
                child: Text("CET Internship System v1.0",
                    style: TextStyle(color: Colors.grey, fontSize: 12)),
              ), // ระยะห่างเผื่อปุ่ม SOS ด้านล่าง
            ],
          ),
        ),
      ),
      // 🚨 เพิ่มปุ่มตรงนี้ ก่อนปิด Scaffold 🚨
      floatingActionButton:
          _activeInternship != null && _activeInternship!['status'] == 'active'
              ? FloatingActionButton.extended(
                  onPressed: _showSOSDialog,
                  backgroundColor: Colors.red.shade600,
                  icon: const Icon(Icons.sos_rounded, color: Colors.white),
                  label: const Text("ฉุกเฉิน",
                      style: TextStyle(
                          color: Colors.white, fontWeight: FontWeight.bold)),
                )
              : null,
    );
  }

  Widget _buildHeader(Color color) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.only(left: 25, right: 25, bottom: 30, top: 10),
      decoration: BoxDecoration(
        color: color,
        borderRadius: const BorderRadius.only(
            bottomLeft: Radius.circular(30), bottomRight: Radius.circular(30)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text("ยินดีต้อนรับ",
              style: TextStyle(
                  color: Colors.white.withOpacity(0.7), fontSize: 14)),
          const SizedBox(height: 5),
          Text(
            _fullname,
            style: const TextStyle(
                color: Colors.white, fontSize: 24, fontWeight: FontWeight.bold),
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
          ),
          Text("รหัสนักศึกษา: $_username",
              style: const TextStyle(color: Colors.white70)),
        ],
      ),
    );
  }

  Widget _buildReadOnlyWarning() {
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 20, vertical: 10),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
          color: Colors.blueGrey.shade50,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: Colors.blueGrey.shade200)),
      child: const Row(
        children: [
          Icon(Icons.info_outline, color: Colors.blueGrey),
          SizedBox(width: 10),
          Expanded(
              child: Text(
                  "ขณะนี้อยู่นอกรอบการฝึกงาน หรือคุณฝึกจบแล้ว ระบบเปิดให้เข้าชมข้อมูลได้อย่างเดียว",
                  style: TextStyle(fontSize: 12, color: Colors.blueGrey))),
        ],
      ),
    );
  }

  Widget _buildInternshipCard() {
    if (_isLoading)
      return const Padding(
          padding: EdgeInsets.all(40),
          child: Center(child: CircularProgressIndicator()));

    if (_hasError) {
      return Container(
        margin: const EdgeInsets.symmetric(horizontal: 20),
        padding: const EdgeInsets.all(25),
        width: double.infinity,
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(20),
          border: Border.all(color: Colors.red.shade300, width: 2),
          boxShadow: [
            BoxShadow(color: Colors.red.withOpacity(0.05), blurRadius: 10)
          ],
        ),
        child: Column(
          children: [
            Icon(Icons.wifi_off_rounded, size: 50, color: Colors.red.shade400),
            const SizedBox(height: 15),
            const Text("การเชื่อมต่อล้มเหลว",
                style: TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.bold,
                    color: Colors.red)),
            const SizedBox(height: 8),
            Text("ไม่สามารถดึงข้อมูลจากระบบได้\nกรุณาตรวจสอบอินเทอร์เน็ตของคุณ",
                style: TextStyle(color: Colors.grey.shade700, fontSize: 14),
                textAlign: TextAlign.center),
            const SizedBox(height: 20),
            ElevatedButton.icon(
              onPressed: _loadInitialData,
              icon: const Icon(Icons.refresh, color: Colors.white),
              label: const Text("โหลดข้อมูลใหม่",
                  style: TextStyle(
                      color: Colors.white, fontWeight: FontWeight.bold)),
              style: ElevatedButton.styleFrom(
                  backgroundColor: Colors.red.shade400,
                  shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(10))),
            )
          ],
        ),
      );
    }

    if (_activeInternship == null) {
      return Container(
        margin: const EdgeInsets.symmetric(horizontal: 20),
        child: InkWell(
          onTap: _isReadOnly
              ? null
              : () async {
                  final selectedCompany = await Navigator.push(
                      context,
                      MaterialPageRoute(
                          builder: (context) => const SearchCompanyScreen()));
                  if (selectedCompany != null && mounted)
                    _confirmRequest(selectedCompany);
                },
          borderRadius: BorderRadius.circular(20),
          child: Container(
            padding: const EdgeInsets.all(30),
            decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(20),
                border: Border.all(color: Colors.grey.shade200),
                boxShadow: [
                  BoxShadow(
                      color: Colors.black.withOpacity(0.03), blurRadius: 10)
                ]),
            child: Column(
              children: [
                Icon(Icons.add_location_alt_rounded,
                    size: 50, color: _isReadOnly ? Colors.grey : Colors.blue),
                const SizedBox(height: 10),
                Text(_isReadOnly ? "ไม่มีรอบการฝึกงาน" : "เลือกสถานที่ฝึกงาน",
                    style: TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.bold,
                        color: _isReadOnly ? Colors.grey : Colors.blue)),
                const SizedBox(height: 5),
                Text(
                    _isReadOnly
                        ? "ไม่สามารถลงทะเบียนได้ในขณะนี้"
                        : "คลิกเพื่อค้นหาสถานประกอบการ",
                    style: const TextStyle(color: Colors.grey, fontSize: 13)),
              ],
            ),
          ),
        ),
      );
    }

    String currentStatus = _activeInternship!['status'] ?? 'active';

    if (currentStatus == 'pending') {
      return Container(
        margin: const EdgeInsets.symmetric(horizontal: 20),
        padding: const EdgeInsets.all(25),
        width: double.infinity,
        decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(20),
            border: Border.all(color: Colors.orange.shade400, width: 2),
            boxShadow: [
              BoxShadow(color: Colors.orange.withOpacity(0.1), blurRadius: 15)
            ]),
        child: Column(
          children: [
            Icon(Icons.assignment_turned_in,
                size: 50, color: Colors.orange.shade600),
            const SizedBox(height: 15),
            const Text("รออาจารย์อนุมัติจบการฝึกงาน",
                style: TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.bold,
                    color: Colors.orange)),
            const SizedBox(height: 8),
            Text(
                "คุณได้ส่งรายงานสรุปเรียบร้อยแล้ว\nกรุณารออาจารย์ตรวจสอบและอนุมัติ",
                style: TextStyle(color: Colors.grey.shade700, fontSize: 14),
                textAlign: TextAlign.center),
          ],
        ),
      );
    }

    if (currentStatus == 'finished') {
      return Container(
        margin: const EdgeInsets.symmetric(horizontal: 20),
        padding: const EdgeInsets.all(25),
        width: double.infinity,
        decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(20),
            border: Border.all(color: Colors.green.shade400, width: 2),
            boxShadow: [
              BoxShadow(color: Colors.green.withOpacity(0.1), blurRadius: 15)
            ]),
        child: Column(
          children: [
            Icon(Icons.check_circle_outline,
                size: 50, color: Colors.green.shade600),
            const SizedBox(height: 15),
            const Text("ผ่านการฝึกงานเรียบร้อย",
                style: TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.bold,
                    color: Colors.green)),
            const SizedBox(height: 8),
            Text(
                "ยินดีด้วย! คุณผ่านการประเมินและจบการฝึกงานที่\n${_activeInternship!['company_name']} แล้ว",
                style: TextStyle(color: Colors.grey.shade700, fontSize: 14),
                textAlign: TextAlign.center),
          ],
        ),
      );
    }

    if (currentStatus == 'relocating') {
      return Container(
        margin: const EdgeInsets.symmetric(horizontal: 20),
        padding: const EdgeInsets.all(25),
        width: double.infinity,
        decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(20),
            border: Border.all(color: Colors.orange.shade400, width: 2),
            boxShadow: [
              BoxShadow(color: Colors.orange.withOpacity(0.1), blurRadius: 15)
            ]),
        child: Column(
          children: [
            Icon(Icons.hourglass_top_rounded,
                size: 50, color: Colors.orange.shade600),
            const SizedBox(height: 15),
            const Text("รออาจารย์อนุมัติคำขอย้าย",
                style: TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.bold,
                    color: Colors.orange)),
            const SizedBox(height: 8),
            Text(
                "ระบบได้รับคำร้องของคุณแล้วจาก:\n${_activeInternship!['company_name']}",
                style: TextStyle(color: Colors.grey.shade700, fontSize: 14),
                textAlign: TextAlign.center),
          ],
        ),
      );
    }

    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 20),
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(20),
          boxShadow: [
            BoxShadow(color: Colors.black.withOpacity(0.05), blurRadius: 10)
          ]),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(children: [
            Icon(Icons.location_on, color: Colors.redAccent.shade200),
            const SizedBox(width: 8),
            const Text("สถานที่ฝึกงานปัจจุบัน",
                style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16))
          ]),
          const Divider(height: 25),
          Text(_activeInternship!['company_name'] ?? "-",
              style: const TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.bold,
                  color: Colors.blue)),
          const SizedBox(height: 8),
          Text(_activeInternship!['address'] ?? "-",
              style: TextStyle(color: Colors.grey.shade600, fontSize: 13)),
          const SizedBox(height: 20),
          SizedBox(
            width: double.infinity,
            child: ElevatedButton.icon(
              onPressed: () => _navigateToLocation(
                  _activeInternship!['latitude'].toString(),
                  _activeInternship!['longitude'].toString()),
              icon: const Icon(Icons.navigation_rounded, color: Colors.white),
              label: const Text("เปิดนำทางด้วย Google Maps",
                  style: TextStyle(color: Colors.white)),
              style: ElevatedButton.styleFrom(
                  backgroundColor: Colors.blue.shade700,
                  padding: const EdgeInsets.symmetric(vertical: 14),
                  shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(10))),
            ),
          ),
        ],
      ),
    );
  }

  // ==========================================
  // ฟังก์ชันโหลดและเปิด PDF (ดูได้เลยแม้ยังไม่มีที่ฝึกงาน)
  // ==========================================
  Future<void> _viewSchedulePdf() async {
    // 🚨 เอาตัวดัก _activeInternship == null ออกไปแล้วครับ! 🚨

    showDialog(
        context: context,
        barrierDismissible: false,
        builder: (_) => const Center(child: CircularProgressIndicator()));

    try {
      // ดึงรอบการฝึกงานทั้งหมดมา
      final response = await http.get(Uri.parse(
          'https://student.cet.rmutr.ac.th/coopcet/internship/app/get_rounds.php'));

      if (!mounted) return;
      Navigator.pop(context); // ปิด Loading

      if (response.statusCode == 200) {
        List<dynamic> rounds = jsonDecode(response.body);

        // 🎯 ดึงปีการศึกษาจากรหัสนักศึกษา (เช่น รหัส 265... -> ปี 2565)
        String studentYear = "";
        if (_username.length >= 3) {
          studentYear = "25${_username.substring(1, 3)}";
        }

        var myRound;
        try {
          // เลือกรอบที่ "ปีการศึกษา" ตรงกับ "ปีของรหัสนักศึกษา"
          myRound = rounds
              .firstWhere((r) => r['academic_year'].toString() == studentYear);
        } catch (e) {
          // ถ้าหาปีตัวเองไม่เจอจริงๆ (แอดมินอาจจะยังไม่สร้าง) ให้ลองดึงรอบที่ open อยู่มาให้ดูก่อน
          try {
            myRound = rounds.firstWhere((r) => r['round_status'] == 'open');
          } catch (e) {
            myRound = null;
          }
        }

        // ตรวจสอบและเปิดไฟล์
        if (myRound != null &&
            myRound['supervision_schedule_pdf'] != null &&
            myRound['supervision_schedule_pdf'].toString().isNotEmpty &&
            myRound['supervision_schedule_pdf'].toString() != 'null') {
          String cleanPath = myRound['supervision_schedule_pdf']
              .toString()
              .replaceAll("../internship/app/", "");
          final Uri url = Uri.parse(
              "https://student.cet.rmutr.ac.th/coopcet/internship/app/$cleanPath");

          if (!await launchUrl(url, mode: LaunchMode.externalApplication)) {
            _showError("ไม่สามารถเปิดไฟล์ได้");
          }
        } else {
          String yearText = myRound != null
              ? myRound['academic_year'].toString()
              : studentYear;
          ScaffoldMessenger.of(context).showSnackBar(SnackBar(
              content: Text(
                  'อาจารย์ยังไม่ได้อัปโหลดกำหนดการนิเทศของปี $yearText ครับ'),
              backgroundColor: Colors.orange));
        }
      }
    } catch (e) {
      if (!mounted) return;
      Navigator.pop(context);
      _showError("เกิดข้อผิดพลาดในการดึงข้อมูลกำหนดการ");
    }
  }

  // ==========================================
  // ฟังก์ชัน SOS ขอความช่วยเหลือฉุกเฉิน (อัปเดต ไม่ต้องส่งเบอร์โทร)
  // ==========================================
  Future<void> _sendSOSAlert() async {
    showDialog(
        context: context,
        barrierDismissible: false,
        builder: (_) => const Center(child: CircularProgressIndicator()));

    try {
      final response = await http.post(
        Uri.parse(
            'https://student.cet.rmutr.ac.th/coopcet/internship/app/sos_alert.php'),
        body: {
          'student_id': _username, // 🚨 ส่งแค่ ID อย่างเดียวพอ
        },
      ).timeout(const Duration(seconds: 15));

      if (!mounted) return;
      Navigator.pop(context); // ปิด Loading

      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        if (data['success']) {
          ScaffoldMessenger.of(context).showSnackBar(SnackBar(
              content: Text(data['message']),
              backgroundColor: Colors.green,
              duration: const Duration(seconds: 4)));
        } else {
          _showError(data['message']);
        }
      } else {
        _showError("เกิดข้อผิดพลาดจากเซิร์ฟเวอร์");
      }
    } catch (e) {
      if (!mounted) return;
      Navigator.pop(context);
      _showError("การเชื่อมต่อล้มเหลว หรือใช้เวลานานเกินไป");
    }
  }

  void _showSOSDialog() {
    // 🚨 เอา TextBox รับเบอร์โทรออก เหลือแค่หน้าต่างยืนยัน
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(15)),
        title: const Row(
          children: [
            Icon(Icons.warning_rounded, color: Colors.red, size: 30),
            SizedBox(width: 10),
            Text("ยืนยันขอความช่วยเหลือ!",
                style: TextStyle(
                    fontWeight: FontWeight.bold,
                    color: Colors.red,
                    fontSize: 18)),
          ],
        ),
        content: const Text(
            "ระบบจะส่งพิกัดสถานที่ฝึกงาน และเบอร์โทรศัพท์ที่บันทึกไว้ ไปยังอาจารย์ทันที\n\nคุณแน่ใจหรือไม่?",
            style: TextStyle(fontSize: 14)),
        actions: [
          TextButton(
              onPressed: () => Navigator.pop(context),
              child: const Text("ยกเลิก",
                  style: TextStyle(
                      color: Colors.grey, fontWeight: FontWeight.bold))),
          ElevatedButton.icon(
            onPressed: () {
              Navigator.pop(context);
              _sendSOSAlert(); // 🚨 ไม่ต้องส่ง parameter แล้ว
            },
            style: ElevatedButton.styleFrom(
                backgroundColor: Colors.red,
                shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(8))),
            icon: const Icon(Icons.send, color: Colors.white, size: 18),
            label: const Text("ส่งสัญญาณ SOS",
                style: TextStyle(
                    color: Colors.white, fontWeight: FontWeight.bold)),
          ),
        ],
      ),
    );
  }

  Widget _buildMenuGrid() {
    if (_hasError) return const SizedBox();

    bool hasNoInternship = _activeInternship == null;
    String currentStatus =
        _activeInternship != null ? _activeInternship!['status'] : '';
    bool isPendingOrFinished =
        (currentStatus == 'pending' || currentStatus == 'finished');

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 20),
      child: GridView.count(
        shrinkWrap: true,
        physics: const NeverScrollableScrollPhysics(),
        crossAxisCount: 2,
        crossAxisSpacing: 15,
        mainAxisSpacing: 15,
        children: [
          // ปุ่มที่ 1: บันทึกรายวัน
          _buildMenuCard(
            "บันทึกรายวัน",
            Icons.edit_note,
            Colors.green,
            () {
              if (_isReadOnly) {
                _showError("ระบบถูกปิด หรือไม่อยู่ในรอบการฝึกงาน");
                return;
              }
              if (hasNoInternship) {
                _showError("กรุณาเลือกสถานที่ฝึกงานก่อน");
              } else if (isPendingOrFinished) {
                _showError(
                    "ไม่สามารถบันทึกรายวันได้ เนื่องจากส่งสรุปหรือฝึกงานจบแล้ว");
              } else {
                Navigator.push(
                    context,
                    MaterialPageRoute(
                        builder: (context) =>
                            DailyLogScreen(studentId: _username)));
              }
            },
            isLocked: _isReadOnly || hasNoInternship || isPendingOrFinished,
          ),

          // ปุ่มที่ 2: ขอย้ายสถานที่
          _buildMenuCard(
            "ขอย้ายสถานที่",
            Icons.move_up,
            Colors.orange,
            () async {
              if (_isReadOnly) {
                _showError("ไม่อยู่ในรอบที่สามารถขอย้ายได้");
                return;
              }
              if (hasNoInternship) {
                _showError("คุณยังไม่มีที่ฝึกงาน หรือส่งคำร้องไปแล้ว");
                return;
              }
              if (isPendingOrFinished) {
                _showError(
                    "ไม่สามารถย้ายสถานที่ได้ เนื่องจากส่งสรุปหรือฝึกงานจบแล้ว");
                return;
              }
              if (currentStatus == 'active') {
                final bool? requestSent = await Navigator.push(
                    context,
                    MaterialPageRoute(
                        builder: (context) => RelocateScreen(
                            internshipId:
                                _activeInternship!['internship_id'].toString(),
                            currentCompanyName:
                                _activeInternship!['company_name'])));
                if (requestSent == true) _loadInitialData();
              } else if (currentStatus == 'relocating') {
                _showError("กำลังรออาจารย์อนุมัติคำขอย้าย");
              }
            },
            isLocked: _isReadOnly || hasNoInternship || isPendingOrFinished,
          ),

          // ปุ่มที่ 3: คลังข้อมูล (ย้ายมาอยู่ใน Grid)
          _buildMenuCard(
            "คลังข้อมูล",
            Icons.archive_rounded,
            Colors.indigo,
            () {
              Navigator.push(
                  context,
                  MaterialPageRoute(
                      builder: (context) => const StudentArchiveScreen()));
            },
            isLocked: false,
          ),

          // 🚨 ปุ่มที่ 4: กำหนดการนิเทศ (เพิ่มใหม่!) 🚨
          _buildMenuCard(
            "กำหนดการนิเทศ", Icons.calendar_month_rounded,
            const Color.fromARGB(255, 14, 210, 214),
            () {
              _viewSchedulePdf(); // เรียกใช้ฟังก์ชันดู PDF
            },
            // ให้กดดูได้ตลอดตราบใดที่ไม่ได้ติด Error (หรือจะล็อคถ้า hasNoInternship ก็ได้ตามต้องการครับ)
            isLocked: false,
          ),
        ],
      ),
    );
  }

  Widget _buildMenuCard(
      String title, IconData icon, Color color, VoidCallback onTap,
      {bool isLocked = false}) {
    Color displayColor = isLocked ? Colors.grey : color;
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(20),
      child: Container(
        decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(20),
            boxShadow: [
              BoxShadow(color: Colors.black.withOpacity(0.05), blurRadius: 10)
            ]),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(icon, size: 40, color: displayColor),
            const SizedBox(height: 10),
            Text(title,
                style: TextStyle(
                    fontWeight: FontWeight.bold,
                    color: isLocked ? Colors.grey : Colors.black87))
          ],
        ),
      ),
    );
  }

  void _showError(String msg) {
    ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(msg), backgroundColor: Colors.red));
  }

  Future<void> _logout() async {
    SharedPreferences prefs = await SharedPreferences.getInstance();
    String? username = prefs.getString('currentUser_username');

    await prefs.clear();

    if (username != null) {
      try {
        await http.post(
            Uri.parse(
                'https://student.cet.rmutr.ac.th/coopcet/internship/app/logout.php'),
            body: {'users_name': username}).timeout(const Duration(seconds: 5));
      } catch (e) {
        debugPrint("Logout Error (Ignored)");
      }
    }

    if (mounted)
      Navigator.pushAndRemoveUntil(
          context,
          MaterialPageRoute(builder: (context) => const LoginScreen()),
          (route) => false);
  }

  void _showLogoutDialog() {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text("ออกจากระบบ?"),
        content: const Text("ยืนยันการออกจากระบบใช่หรือไม่?"),
        actions: [
          TextButton(
              onPressed: () => Navigator.pop(context),
              child: const Text("ยกเลิก")),
          ElevatedButton(
              onPressed: _logout,
              style: ElevatedButton.styleFrom(backgroundColor: Colors.blue),
              child: const Text("ตกลง", style: TextStyle(color: Colors.white))),
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
