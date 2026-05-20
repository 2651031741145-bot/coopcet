import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'dart:convert';

class RelocateScreen extends StatefulWidget {
  final String internshipId;
  final String currentCompanyName;

  const RelocateScreen({
    Key? key, 
    required this.internshipId, 
    required this.currentCompanyName
  }) : super(key: key);

  @override
  State<RelocateScreen> createState() => _RelocateScreenState();
}

class _RelocateScreenState extends State<RelocateScreen> {
  final _reasonController = TextEditingController();
  final _formKey = GlobalKey<FormState>();
  bool _isSubmitting = false;

  Future<void> _submitRelocateRequest() async {
    if (!_formKey.currentState!.validate()) return;

    // แจ้งเตือนยืนยันก่อนส่ง
    bool confirm = await showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text("ยืนยันการขอย้าย?", style: TextStyle(color: Colors.orange, fontWeight: FontWeight.bold)),
        content: const Text("หากส่งคำร้องแล้ว คุณจะต้องรอให้อาจารย์อนุมัติก่อน จึงจะสามารถเลือกสถานที่ฝึกงานใหม่ได้ ยืนยันหรือไม่?"),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text("ยกเลิก", style: TextStyle(color: Colors.grey))),
          ElevatedButton(
            onPressed: () => Navigator.pop(context, true),
            style: ElevatedButton.styleFrom(backgroundColor: Colors.orange),
            child: const Text("ยืนยันส่งคำร้อง", style: TextStyle(color: Colors.white)),
          ),
        ],
      ),
    ) ?? false;

    if (!confirm) return;

    setState(() => _isSubmitting = true);

    try {
      final response = await http.post(
        Uri.parse('https://student.cet.rmutr.ac.th/coopcet/internship/app/request_relocate.php'),
        body: {
          'internship_id': widget.internshipId,
          'reason': _reasonController.text.trim(),
        },
      );

      final data = jsonDecode(response.body);

      if (mounted) {
        if (data['success']) {
          ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(data['message']), backgroundColor: Colors.green));
          // ส่งค่า true กลับไปให้หน้า Dashboard รีเฟรช
          Navigator.pop(context, true); 
        } else {
          ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(data['message']), backgroundColor: Colors.red));
        }
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text("เชื่อมต่อเซิร์ฟเวอร์ล้มเหลว"), backgroundColor: Colors.red));
      }
    } finally {
      if (mounted) setState(() => _isSubmitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('ขอย้ายสถานที่ฝึกงาน'),
        backgroundColor: Colors.orange.shade700,
        foregroundColor: Colors.white,
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(20),
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // ข้อมูลบริษัทปัจจุบัน
              Container(
                padding: const EdgeInsets.all(15),
                width: double.infinity,
                decoration: BoxDecoration(
                  color: Colors.orange.shade50,
                  borderRadius: BorderRadius.circular(15),
                  border: Border.all(color: Colors.orange.shade200)
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text("สถานที่ฝึกงานปัจจุบัน:", style: TextStyle(color: Colors.orange, fontWeight: FontWeight.bold)),
                    const SizedBox(height: 5),
                    Text(widget.currentCompanyName, style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
                  ],
                ),
              ),
              const SizedBox(height: 25),

              const Text("ระบุเหตุผลในการขอย้าย *", style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
              const SizedBox(height: 10),
              TextFormField(
                controller: _reasonController,
                maxLines: 5,
                decoration: InputDecoration(
                  hintText: "เช่น งานไม่ตรงสาย, เดินทางลำบาก, บริษัทปิดกิจการ...",
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
                  filled: true,
                  fillColor: Colors.white,
                ),
                validator: (value) => value!.trim().isEmpty ? 'กรุณาระบุเหตุผลให้อาจารย์พิจารณา' : null,
              ),
              const SizedBox(height: 30),

              SizedBox(
                width: double.infinity,
                child: ElevatedButton.icon(
                  onPressed: _isSubmitting ? null : _submitRelocateRequest,
                  icon: _isSubmitting ? const SizedBox() : const Icon(Icons.send, color: Colors.white),
                  label: _isSubmitting 
                    ? const CircularProgressIndicator(color: Colors.white)
                    : const Text("ส่งคำร้องขอย้าย", style: TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.bold)),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: Colors.orange.shade700,
                    padding: const EdgeInsets.symmetric(vertical: 15),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10))
                  ),
                ),
              )
            ],
          ),
        ),
      ),
    );
  }
}