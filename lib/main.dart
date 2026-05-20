import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'logregis/login.dart'; 
import 'admin/homeadmin.dart'; 
import 'teacher/hometeacher.dart'; 
import 'student/homestudent.dart'; // แก้ path เป็น student

void main() {
  runApp(const MainApp());
}

class MainApp extends StatefulWidget {
  const MainApp({super.key});

  @override
  State<MainApp> createState() => _MainAppState();
}

class _MainAppState extends State<MainApp> {
  Widget _defaultHome = LoginScreen(); 
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _checkLoginStatus();
  }

  Future<void> _checkLoginStatus() async {
    try {
      SharedPreferences prefs = await SharedPreferences.getInstance();
      
      String? token = prefs.getString('token');
      String? userLevel = prefs.getString('currentUser_level');
      
      // เก็บค่าไว้ก่อนได้ครับ อนาคตเราต้องส่งไปโชว์ในหน้า Home แน่นอน
      // String? username = prefs.getString('currentUser_username');
      // String? fullname = prefs.getString('currentUser_fullname');

      if (token != null && token.isNotEmpty && userLevel != null) {
        setState(() {
          if (userLevel == 'a') {
            _defaultHome = const HomeAdmin(); // เรียกใช้หน้าโล่งๆ ที่เพิ่งสร้าง
          } else if (userLevel == 't') {
            _defaultHome = const HomeTeacher(); 
          } else if (userLevel == 's') { // เปลี่ยนจาก 'u' เป็น 's' ให้ตรงกับ Database
             _defaultHome = const HomeStudent();  
          }
        });
      }
    } catch (e) {
      debugPrint("Error checking login status: $e");
    } finally {
      setState(() {
        _isLoading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_isLoading) {
      return const MaterialApp(
        debugShowCheckedModeBanner: false,
        home: Scaffold(
          body: Center(child: CircularProgressIndicator()),
        ),
      );
    }

    return MaterialApp(
      debugShowCheckedModeBanner: false,
      title: 'Internship App',
      
      // --- [Theme ใช้ของเดิมที่คุณตั้งไว้เลยครับ สวยและคลีนดีมาก] ---
      theme: ThemeData(
        useMaterial3: true,
        
        colorScheme: ColorScheme.fromSeed(
          seedColor: Colors.brown, 
        ).copyWith(
          surface: Colors.white,
          surfaceTint: Colors.transparent,
        ),

        scaffoldBackgroundColor: Colors.grey[100],

        bottomSheetTheme: const BottomSheetThemeData(
          backgroundColor: Colors.white,
          surfaceTintColor: Colors.transparent, 
        ),

        dialogTheme: const DialogTheme(
          backgroundColor: Colors.white,
          surfaceTintColor: Colors.transparent,
        ),

        cardTheme: const CardTheme(
          color: Colors.white,
          surfaceTintColor: Colors.transparent,
        ),

        drawerTheme: const DrawerThemeData(
          backgroundColor: Colors.white,
          surfaceTintColor: Colors.transparent,
        ),
      ),
      
      home: _defaultHome, 
    );
  }
}