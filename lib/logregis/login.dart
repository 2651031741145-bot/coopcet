import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'dart:convert';
import 'package:shared_preferences/shared_preferences.dart';

// เช็คพาธให้ตรงกับโครงสร้างโฟลเดอร์ใหม่
import '../admin/homeadmin.dart';
import '../teacher/hometeacher.dart';
import '../student/homestudent.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({Key? key}) : super(key: key);

  @override
  _LoginScreenState createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final TextEditingController _usernameController = TextEditingController();
  final TextEditingController _passwordController = TextEditingController();
  final _formKey = GlobalKey<FormState>();

  bool _isLoading = true;
  bool _obscureText = true;

  TableRow _buildCreatorTableRow(String id, String firstName, String lastName) {
    final textStyle = TextStyle(
      color: Colors.grey[600],
      fontSize: 14,
    );

    return TableRow(
      children: [
        Padding(
          padding: const EdgeInsets.only(right: 15, bottom: 4),
          child: Text(
            id,
            style: textStyle.copyWith(fontFamily: 'monospace'),
          ),
        ),
        Padding(
          padding: const EdgeInsets.only(right: 15, bottom: 4),
          child: Text(
            firstName,
            style: textStyle,
          ),
        ),
        Padding(
          padding: const EdgeInsets.only(bottom: 4),
          child: Text(
            lastName,
            style: textStyle,
          ),
        ),
      ],
    );
  }

  @override
  void initState() {
    super.initState();
    _checkAutoLogin();
  }

  // --- Session Check (Auto Login) ---
  Future<void> _checkAutoLogin() async {
    SharedPreferences prefs = await SharedPreferences.getInstance();
    String? sessionToken = prefs.getString('token');

    if (sessionToken == null) {
      if (mounted) setState(() => _isLoading = false);
      return;
    }

    String? currentUsername = prefs.getString('currentUser_username');
    if (currentUsername == null) {
      if (mounted) setState(() => _isLoading = false);
      return;
    }

    try {
      // 🚨 เพิ่ม .timeout() ป้องกันแอปค้างตอนเปิดแอป
      final response = await http.post(
        Uri.parse(
            'https://student.cet.rmutr.ac.th/coopcet/internship/app/check_session.php'),
        body: {
          'users_name': currentUsername,
          'token': sessionToken,
        },
      ).timeout(const Duration(seconds: 20));

      if (response.statusCode == 200) {
        var data = jsonDecode(response.body);
        if (data['status'] == 'active') {
          String userLevel = prefs.getString('currentUser_level') ?? 's';

          if (mounted) {
            _navigateToHome(userLevel);
          }
          return; // ถ้านำทางไปหน้าอื่นแล้ว ไม่ต้องรันโค้ดข้างล่างต่อ
        } else {
          await prefs.remove('token');
        }
      }
    } catch (e) {
      debugPrint("Check Session Error: $e");
      // ถ้า Error ก็แค่ไม่ต้อง Auto Login ให้ผู้ใช้กรอกรหัสใหม่
    } finally {
      // 🚨 บังคับปิดกล่องโหลดเสมอ ไม่ว่าจะสำเร็จหรือพัง
      if (mounted) setState(() => _isLoading = false);
    }
  }

  // --- Navigation Logic ---
  void _navigateToHome(String userLevel) {
    if (userLevel == 't') {
      Navigator.pushReplacement(
        context,
        MaterialPageRoute(builder: (context) => const HomeTeacher()),
      );
    } else if (userLevel == 'a') {
      Navigator.pushReplacement(
        context,
        MaterialPageRoute(builder: (context) => const HomeAdmin()),
      );
    } else {
      Navigator.pushReplacement(
        context,
        MaterialPageRoute(builder: (context) => const HomeStudent()),
      );
    }
  }

  // --- Login API Call ---
  Future<void> loginUser() async {
    if (!_formKey.currentState!.validate()) return;

    setState(() => _isLoading = true);

    try {
      // 🚨 เพิ่ม .timeout() ป้องกันแอปค้างตอนกดปุ่มเข้าสู่ระบบ
      final response = await http.post(
        Uri.parse(
            'https://student.cet.rmutr.ac.th/coopcet/internship/app/login.php'),
        body: {
          'users_name': _usernameController.text.trim(),
          'password': _passwordController.text.trim(),
        },
      ).timeout(const Duration(seconds: 20));

      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        if (data['success'] == true) {
          SharedPreferences prefs = await SharedPreferences.getInstance();
          await prefs.setString(
              'currentUser_username', data['users_name'] ?? '');
          await prefs.setString('currentUser_fullname', data['fullname'] ?? '');
          await prefs.setString('currentUser_level', data['user_level'] ?? 's');
          await prefs.setString(
              'currentUser_academicYear', data['academic_year'] ?? '');
          await prefs.setString('token', data['token'] ?? '');

          _navigateToHome(data['user_level'] ?? 's');
          return; // เข้าสู่ระบบสำเร็จแล้ว ไม่ต้องทำ finally เพื่อปิดกล่องโหลด (เพราะเปลี่ยนหน้าไปแล้ว)
        } else {
          _showErrorSnackBar(data['message'] ?? 'เข้าสู่ระบบไม่สำเร็จ');
        }
      } else {
        _showErrorSnackBar('Server Error (${response.statusCode})');
      }
    } catch (e) {
      debugPrint("Login Error: $e");
      // 🚨 ดักจับ Error แจ้งเตือนผู้ใช้แทนการหมุนค้าง
      _showErrorSnackBar('การเชื่อมต่อล้มเหลว หรือใช้เวลานานเกินไป');
    } finally {
      // 🚨 บังคับให้ปิดกล่อง Loading เสมอ ถ้าการเข้าสู่ระบบไม่สำเร็จ
      if (mounted) setState(() => _isLoading = false);
    }
  }

  void _showErrorSnackBar(String message) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(message), backgroundColor: Colors.red),
    );
  }

  @override
  Widget build(BuildContext context) {
    final primaryColor = const Color(0xFF6D4C41);
    final secondaryColor = const Color(0xFF8D6E63);

    return Scaffold(
      backgroundColor: Colors.grey[100],
      body: _isLoading
          ? Center(child: CircularProgressIndicator(color: primaryColor))
          : SingleChildScrollView(
              child: Column(
                children: [
                  // --- Header ---
                  Container(
                    height: 280,
                    decoration: BoxDecoration(
                        gradient: LinearGradient(
                          colors: [primaryColor, secondaryColor],
                          begin: Alignment.topLeft,
                          end: Alignment.bottomRight,
                        ),
                        borderRadius: const BorderRadius.only(
                          bottomLeft: Radius.circular(50),
                          bottomRight: Radius.circular(50),
                        ),
                        boxShadow: const [
                          BoxShadow(
                              color: Colors.black26,
                              blurRadius: 10,
                              offset: Offset(0, 5))
                        ]),
                    child: Center(
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          Container(
                            padding: const EdgeInsets.all(10),
                            decoration: const BoxDecoration(
                                color: Colors.white,
                                shape: BoxShape.circle,
                                boxShadow: [
                                  BoxShadow(
                                      color: Colors.black12, blurRadius: 10)
                                ]),
                            child: Image.asset('assets/logo1.jpg',
                                height: 80, width: 80),
                          ),
                          const SizedBox(height: 15),
                          const Text(
                            "CET INTERNSHIP",
                            style: TextStyle(
                              fontSize: 28,
                              fontWeight: FontWeight.bold,
                              color: Colors.white,
                              letterSpacing: 1.5,
                            ),
                          ),
                          const Text(
                            "ระบบจัดการคลังสหกิจ",
                            style: TextStyle(
                              fontSize: 16,
                              color: Colors.white70,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),

                  const SizedBox(height: 30),

                  // --- Form Card ---
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 20),
                    child: Card(
                      elevation: 5,
                      shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(20)),
                      child: Padding(
                        padding: const EdgeInsets.all(25.0),
                        child: Form(
                          key: _formKey,
                          child: Column(
                            children: [
                              Text(
                                "เข้าสู่ระบบ",
                                style: TextStyle(
                                  fontSize: 22,
                                  fontWeight: FontWeight.bold,
                                  color: primaryColor,
                                ),
                              ),
                              const SizedBox(height: 25),

                              // Username Field
                              TextFormField(
                                controller: _usernameController,
                                decoration: InputDecoration(
                                  labelText: 'รหัสนักศึกษา / ชื่อผู้ใช้',
                                  prefixIcon:
                                      Icon(Icons.person, color: primaryColor),
                                  border: OutlineInputBorder(
                                      borderRadius: BorderRadius.circular(15)),
                                  filled: true,
                                  fillColor: Colors.grey[50],
                                ),
                                validator: (val) =>
                                    val!.isEmpty ? 'กรุณากรอกชื่อผู้ใช้' : null,
                              ),
                              const SizedBox(height: 20),

                              // Password Field
                              TextFormField(
                                controller: _passwordController,
                                obscureText: _obscureText,
                                decoration: InputDecoration(
                                  labelText: 'รหัสผ่าน',
                                  prefixIcon:
                                      Icon(Icons.lock, color: primaryColor),
                                  suffixIcon: IconButton(
                                    icon: Icon(
                                        _obscureText
                                            ? Icons.visibility
                                            : Icons.visibility_off,
                                        color: Colors.grey),
                                    onPressed: () => setState(
                                        () => _obscureText = !_obscureText),
                                  ),
                                  border: OutlineInputBorder(
                                      borderRadius: BorderRadius.circular(15)),
                                  filled: true,
                                  fillColor: Colors.grey[50],
                                ),
                                validator: (val) =>
                                    val!.isEmpty ? 'กรุณากรอกรหัสผ่าน' : null,
                              ),
                              const SizedBox(height: 30),

                              // Login Button
                              SizedBox(
                                width: double.infinity,
                                height: 50,
                                child: ElevatedButton(
                                  onPressed: loginUser,
                                  style: ElevatedButton.styleFrom(
                                    backgroundColor: primaryColor,
                                    shape: RoundedRectangleBorder(
                                        borderRadius:
                                            BorderRadius.circular(15)),
                                    elevation: 3,
                                  ),
                                  child: const Text(
                                    "เข้าสู่ระบบ",
                                    style: TextStyle(
                                        fontSize: 18,
                                        fontWeight: FontWeight.bold,
                                        color: Colors.white),
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(height: 220),

                  const Padding(
                    padding: EdgeInsets.only(bottom: 20),
                    child: Text("CET Internship System v1.0",
                        style: TextStyle(color: Colors.grey)),
                  ),
                ],
              ),
            ),
    );
  }
}
