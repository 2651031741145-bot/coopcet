import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'dart:convert';

class EditUserForm extends StatefulWidget {
  final Map<String, dynamic> user;

  const EditUserForm({Key? key, required this.user}) : super(key: key);

  @override
  State<EditUserForm> createState() => _EditUserFormState();
}

class _EditUserFormState extends State<EditUserForm> {
  final _formKey = GlobalKey<FormState>();
  late TextEditingController _fullnameController;
  final _passwordController = TextEditingController(); // Controller สำหรับรหัสผ่านใหม่
  late String _selectedLevel;
  bool _isSaving = false;
  bool _obscureText = true;

  @override
  void initState() {
    super.initState();
    _fullnameController = TextEditingController(text: widget.user['full_name']);
    _selectedLevel = widget.user['user_level'];
  }

  Future<void> _updateUser() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _isSaving = true);

    try {
      final response = await http.post(
        Uri.parse('https://student.cet.rmutr.ac.th/coopcet/internship/app/update_user.php'),
        body: {
          'users_name': widget.user['users_name'],
          'full_name': _fullnameController.text.trim(),
          'user_level': _selectedLevel,
          'password': _passwordController.text.trim(), // ส่งรหัสผ่านใหม่ไปด้วย (ถ้ามี)
        },
      );

      final data = jsonDecode(response.body);
      if (data['success'] == true) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('แก้ไขข้อมูลสำเร็จ')));
          Navigator.pop(context, true);
        }
      }
    } catch (e) {
      debugPrint("Update Error: $e");
    } finally {
      if (mounted) setState(() => _isSaving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('แก้ไขข้อมูลสมาชิก'), backgroundColor: const Color(0xFF6D4C41)),
      body: SingleChildScrollView( // เพิ่มเพื่อให้เลื่อนหน้าจอได้เวลาคีย์บอร์ดเด้ง
        padding: const EdgeInsets.all(20.0),
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text("ID: ${widget.user['users_name']}", style: const TextStyle(fontSize: 16, color: Colors.grey, fontWeight: FontWeight.bold)),
              const SizedBox(height: 20),
              
              // แก้ไขชื่อ-นามสกุล
              TextFormField(
                controller: _fullnameController,
                decoration: const InputDecoration(labelText: 'ชื่อ-นามสกุล', border: OutlineInputBorder(), prefixIcon: Icon(Icons.person)),
                validator: (val) => val!.isEmpty ? 'กรุณากรอกชื่อ' : null,
              ),
              const SizedBox(height: 20),
              
              // แก้ไขรหัสผ่าน (Optional)
              TextFormField(
                controller: _passwordController,
                obscureText: _obscureText,
                decoration: InputDecoration(
                  labelText: 'รหัสผ่านใหม่ (เว้นว่างไว้ถ้าไม่ต้องการเปลี่ยน)',
                  hintText: 'กรอกรหัสผ่านใหม่ที่นี่',
                  border: const OutlineInputBorder(),
                  prefixIcon: const Icon(Icons.lock),
                  suffixIcon: IconButton(
                    icon: Icon(_obscureText ? Icons.visibility : Icons.visibility_off),
                    onPressed: () => setState(() => _obscureText = !_obscureText),
                  ),
                ),
              ),
              const SizedBox(height: 10),
              const Text("* หากไม่ต้องการเปลี่ยนรหัสผ่าน ให้ปล่อยช่องนี้ว่างไว้", style: TextStyle(color: Colors.orange, fontSize: 12)),
              const SizedBox(height: 20),
              
              // แก้ไขระดับสิทธิ์
              DropdownButtonFormField(
                value: _selectedLevel,
                decoration: const InputDecoration(labelText: 'ระดับสิทธิ์', border: OutlineInputBorder(), prefixIcon: Icon(Icons.layers)),
                items: const [
                  DropdownMenuItem(value: 's', child: Text('นักศึกษา (S)')),
                  DropdownMenuItem(value: 't', child: Text('อาจารย์ (T)')),
                  DropdownMenuItem(value: 'a', child: Text('แอดมิน (A)')),
                ],
                onChanged: (val) => setState(() => _selectedLevel = val.toString()),
              ),
              const SizedBox(height: 30),
              
              SizedBox(
                width: double.infinity,
                height: 50,
                child: ElevatedButton(
                  onPressed: _isSaving ? null : _updateUser,
                  style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFF6D4C41), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10))),
                  child: _isSaving 
                    ? const CircularProgressIndicator(color: Colors.white) 
                    : const Text("บันทึกการแก้ไข", style: TextStyle(color: Colors.white, fontSize: 18)),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}