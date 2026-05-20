import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'dart:convert';

class AddUserForm extends StatefulWidget {
  final String level; // รับค่า 'a' หรือ 't' เพื่อรู้ว่ากำลังเพิ่มใคร

  const AddUserForm({Key? key, required this.level}) : super(key: key);

  @override
  State<AddUserForm> createState() => _AddUserFormState();
}

class _AddUserFormState extends State<AddUserForm> {
  final _formKey = GlobalKey<FormState>();
  final _usernameController = TextEditingController();
  final _fullnameController = TextEditingController();
  bool _isSaving = false;

  Future<void> _saveUser() async {
    if (!_formKey.currentState!.validate()) return;

    setState(() => _isSaving = true);

    try {
      final response = await http.post(
        Uri.parse('https://student.cet.rmutr.ac.th/coopcet/internship/app/add_user.php'),
        body: {
          'users_name': _usernameController.text.trim(),
          'full_name': _fullnameController.text.trim(),
          'user_level': widget.level, // 'a' หรือ 't'
        },
      );

      final data = jsonDecode(response.body);
      if (data['success'] == true) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('บันทึกข้อมูลเรียบร้อยแล้ว')),
          );
          Navigator.pop(context, true); // กลับหน้าเดิมพร้อมแจ้งว่ามีการอัปเดต
        }
      } else {
        _showError(data['message']);
      }
    } catch (e) {
      _showError("ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้");
    } finally {
      if (mounted) setState(() => _isSaving = false);
    }
  }

  void _showError(String msg) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(msg), backgroundColor: Colors.red),
    );
  }

  @override
  Widget build(BuildContext context) {
    String title = widget.level == 'a' ? "เพิ่มแอดมิน" : "เพิ่มอาจารย์";

    return Scaffold(
      appBar: AppBar(title: Text(title), backgroundColor: const Color(0xFF6D4C41)),
      body: Padding(
        padding: const EdgeInsets.all(20.0),
        child: Form(
          key: _formKey,
          child: Column(
            children: [
              TextFormField(
                controller: _usernameController,
                decoration: const InputDecoration(
                  labelText: 'Username (ใช้เป็นรหัสผ่านเริ่มต้นด้วย)',
                  border: OutlineInputBorder(),
                ),
                validator: (val) => val!.isEmpty ? 'กรุณากรอก Username' : null,
              ),
              const SizedBox(height: 20),
              TextFormField(
                controller: _fullnameController,
                decoration: const InputDecoration(
                  labelText: 'ชื่อ-นามสกุลจริง',
                  border: OutlineInputBorder(),
                ),
                validator: (val) => val!.isEmpty ? 'กรุณากรอกชื่อ-นามสกุล' : null,
              ),
              const SizedBox(height: 30),
              SizedBox(
                width: double.infinity,
                height: 50,
                child: ElevatedButton(
                  onPressed: _isSaving ? null : _saveUser,
                  style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFF6D4C41)),
                  child: _isSaving
                      ? const CircularProgressIndicator(color: Colors.white)
                      : const Text("บันทึกข้อมูล", style: TextStyle(color: Colors.white, fontSize: 18)),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}