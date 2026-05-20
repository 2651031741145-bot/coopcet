import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'dart:convert';
import 'edit_user_form.dart';
import 'add_user_form.dart';

class UserManagement extends StatefulWidget {
  const UserManagement({Key? key}) : super(key: key);

  @override
  State<UserManagement> createState() => _UserManagementState();
}

class _UserManagementState extends State<UserManagement> {
  List<dynamic> _allUsers = [];
  bool _isLoading = true;
  
  // ตัวแปรสำหรับระบบค้นหา
  bool _isSearching = false;
  final TextEditingController _searchController = TextEditingController();
  String _searchQuery = "";

  @override
  void initState() {
    super.initState();
    _fetchUsers();
    
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

  Future<void> _fetchUsers() async {
    setState(() => _isLoading = true);
    try {
      final response = await http.get(
        Uri.parse('https://student.cet.rmutr.ac.th/coopcet/internship/app/get_users.php'),
      );
      if (response.statusCode == 200) {
        setState(() {
          _allUsers = jsonDecode(response.body);
          _isLoading = false;
        });
      }
    } catch (e) {
      debugPrint("Fetch Users Error: $e");
      setState(() => _isLoading = false);
    }
  }

  // 🚨 เพิ่มฟังก์ชันลบผู้ใช้พร้อม Popup ยืนยัน
  Future<void> _deleteUser(String username, String fullName) async {
    // 1. เด้ง Popup ถามเพื่อความชัวร์
    bool? confirm = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Row(
          children: [
            Icon(Icons.warning_amber_rounded, color: Colors.red),
            SizedBox(width: 8),
            Text('ยืนยันการลบ', style: TextStyle(color: Colors.red, fontWeight: FontWeight.bold)),
          ],
        ),
        content: Text('คุณต้องการลบผู้ใช้\n"$fullName"\n(ID: $username) ใช่หรือไม่?\n\n*ข้อมูลที่ถูกลบจะไม่สามารถกู้คืนได้'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false), // ส่งค่า false กลับไป (ยกเลิก)
            child: const Text('ยกเลิก', style: TextStyle(color: Colors.grey, fontWeight: FontWeight.bold)),
          ),
          ElevatedButton(
            style: ElevatedButton.styleFrom(backgroundColor: Colors.red),
            onPressed: () => Navigator.pop(context, true), // ส่งค่า true กลับไป (ยืนยันลบ)
            child: const Text('ลบทิ้ง', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
          ),
        ],
      ),
    );

    // 2. ถ้ากดยืนยัน (true) ให้ยิง API ไปลบที่ฐานข้อมูล
    if (confirm == true) {
      try {
        final response = await http.post(
          Uri.parse('https://student.cet.rmutr.ac.th/coopcet/internship/app/delete_user.php'),
          body: {'users_name': username},
        );
        final data = jsonDecode(response.body);
        if (data['success']) {
          if (mounted) {
            ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(data['message']), backgroundColor: Colors.green));
            _fetchUsers(); // โหลดข้อมูลใหม่เพื่อให้รายชื่ออัปเดต
          }
        } else {
          if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(data['message']), backgroundColor: Colors.red));
        }
      } catch (e) {
        if (mounted) ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('เกิดข้อผิดพลาดในการเชื่อมต่อ'), backgroundColor: Colors.red));
      }
    }
  }

  // ปรับปรุงลอจิกการกรองข้อมูล (Filter + Search)
  List<dynamic> _filterUsers(String level) {
    // 1. กรองตามระดับ (a, t, s)
    List<dynamic> filtered = _allUsers.where((user) => user['user_level'] == level).toList();
    
    // 2. กรองตามคำค้นหา (ถ้ามี)
    if (_searchQuery.isNotEmpty) {
      filtered = filtered.where((user) {
        final fullName = user['full_name'].toString().toLowerCase();
        final userName = user['users_name'].toString().toLowerCase();
        final query = _searchQuery.toLowerCase();
        
        // ค้นหาได้ทั้งชื่อและ ID
        return fullName.contains(query) || userName.contains(query);
      }).toList();
    }
    return filtered;
  }

  @override
  Widget build(BuildContext context) {
    final primaryColor = const Color(0xFF6D4C41);

    return DefaultTabController(
      length: 3,
      child: Scaffold(
        appBar: AppBar(
          // เปลี่ยน Title เป็นช่องค้นหาเมื่อกดปุ่ม Search
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
            : const Text('จัดการสมาชิก'),
          backgroundColor: primaryColor,
          foregroundColor: Colors.white,
          actions: [
            // ปุ่มสลับโหมดค้นหา
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
        body: _isLoading
            ? const Center(child: CircularProgressIndicator())
            : TabBarView(
                children: [
                  _buildUserList(_filterUsers('a')),
                  _buildUserList(_filterUsers('t')),
                  _buildUserList(_filterUsers('s')),
                ],
              ),
        floatingActionButton: FloatingActionButton.extended(
          onPressed: () => _showAddUserMenu(),
          backgroundColor: primaryColor,
          icon: const Icon(Icons.add, color: Colors.white),
          label: const Text("เพิ่มสมาชิก", style: TextStyle(color: Colors.white)),
        ),
      ),
    );
  }

  Widget _buildUserList(List<dynamic> users) {
    if (users.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.search_off, size: 60, color: Colors.grey[400]),
            const SizedBox(height: 10),
            Text(_searchQuery.isEmpty ? "ไม่พบข้อมูลสมาชิก" : "ไม่พบข้อมูลที่ค้นหา",
                style: TextStyle(color: Colors.grey[600])),
          ],
        ),
      );
    }
    return ListView.builder(
      itemCount: users.length,
      itemBuilder: (context, index) {
        final user = users[index];
        return Card(
          margin: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(15)),
          elevation: 2,
          child: ListTile(
            leading: CircleAvatar(
              backgroundColor: const Color(0xFFD7CCC8),
              child: Text(user['user_level'].toUpperCase(), 
                  style: const TextStyle(color: Color(0xFF6D4C41), fontWeight: FontWeight.bold)),
            ),
            title: Text(user['full_name'], style: const TextStyle(fontWeight: FontWeight.bold)),
            subtitle: Text("ID: ${user['users_name']}"),
            // 🚨 แก้ไขส่วน trailing เพื่อให้แสดงปุ่มเรียงกัน 2 ปุ่ม (แก้ไข และ ลบ)
            trailing: Row(
              mainAxisSize: MainAxisSize.min, // ให้ Row กินพื้นที่แคบที่สุดเท่าที่จำเป็น
              children: [
                IconButton(
                  icon: const Icon(Icons.edit, color: Colors.blue),
                  tooltip: 'แก้ไข',
                  onPressed: () async {
                    bool? updated = await Navigator.push(
                      context,
                      MaterialPageRoute(builder: (context) => EditUserForm(user: user)),
                    );
                    if (updated == true) _fetchUsers();
                  },
                ),
                IconButton(
                  icon: const Icon(Icons.delete, color: Colors.red),
                  tooltip: 'ลบผู้ใช้',
                  onPressed: () => _deleteUser(user['users_name'].toString(), user['full_name'].toString()),
                ),
              ],
            ),
          ),
        );
      },
    );
  }

  void _showAddUserMenu() {
    showModalBottomSheet(
      context: context,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (context) {
        return Padding(
          padding: const EdgeInsets.symmetric(vertical: 20),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Text("เลือกประเภทสมาชิกที่ต้องการเพิ่ม",
                  style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
              const SizedBox(height: 10),
              ListTile(
                leading: const Icon(Icons.school, color: Colors.green),
                title: const Text("เพิ่มรายชื่ออาจารย์ (Teacher)"),
                onTap: () async {
                  Navigator.pop(context);
                  // ไปหน้าฟอร์มพร้อมส่งค่า 't'
                  bool? result = await Navigator.push(
                      context,
                      MaterialPageRoute(
                          builder: (context) => const AddUserForm(level: 't')));
                  if (result == true)
                    _fetchUsers(); // โหลดรายชื่อใหม่ถ้าบันทึกสำเร็จ
                },
              ),
              ListTile(
                leading:
                    const Icon(Icons.admin_panel_settings, color: Colors.red),
                title: const Text("เพิ่มรายชื่อแอดมิน (Admin)"),
                onTap: () async {
                  Navigator.pop(context);
                  // ไปหน้าฟอร์มพร้อมส่งค่า 'a'
                  bool? result = await Navigator.push(
                      context,
                      MaterialPageRoute(
                          builder: (context) => const AddUserForm(level: 'a')));
                  if (result == true) _fetchUsers(); // โหลดรายชื่อใหม่
                },
              ),
            ],
          ),
        );
      },
    );
  }
}