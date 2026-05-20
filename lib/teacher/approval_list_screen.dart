import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'dart:convert';
import 'approval_detail_screen.dart'; // อย่าลืม Import หน้าใหม่เข้ามาด้วยนะครับ

class ApprovalListScreen extends StatefulWidget {
  const ApprovalListScreen({Key? key}) : super(key: key);

  @override
  State<ApprovalListScreen> createState() => _ApprovalListScreenState();
}

class _ApprovalListScreenState extends State<ApprovalListScreen> {
  List<dynamic> _pendingList = [];
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _fetchPendingRequests();
  }

  Future<void> _fetchPendingRequests() async {
    setState(() => _isLoading = true);
    try {
      final response = await http.get(
        Uri.parse('https://student.cet.rmutr.ac.th/coopcet/internship/app/get_pending_approvals.php'),
      );
      final data = jsonDecode(response.body);
      if (mounted) {
        setState(() {
          _pendingList = data['success'] ? data['data'] : [];
          _isLoading = false;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() => _isLoading = false);
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text("ดึงข้อมูลไม่สำเร็จ")));
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.grey[50],
      appBar: AppBar(
        title: const Text("รายการขอจบฝึกงาน"),
        backgroundColor: Colors.orange.shade700,
        foregroundColor: Colors.white,
      ),
      body: _isLoading 
        ? const Center(child: CircularProgressIndicator()) 
        : _pendingList.isEmpty
          ? Center(child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [Icon(Icons.inbox, size: 80, color: Colors.grey.shade300), const SizedBox(height: 15), Text("ไม่มีคำขอจบการฝึกงาน", style: TextStyle(color: Colors.grey.shade600, fontSize: 16))]))
          : ListView.builder(
              padding: const EdgeInsets.all(15),
              itemCount: _pendingList.length,
              itemBuilder: (context, index) {
                final item = _pendingList[index];
                return Card(
                  elevation: 2,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(15)),
                  margin: const EdgeInsets.only(bottom: 15),
                  child: Padding(
                    padding: const EdgeInsets.all(20),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Text("รหัส: ${item['student_id']}", style: TextStyle(color: Colors.orange.shade800, fontWeight: FontWeight.bold)),
                            const Icon(Icons.assignment_ind, color: Colors.orange),
                          ],
                        ),
                        const Divider(),
                        Text(item['full_name'], style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
                        const SizedBox(height: 5),
                        Text("📍 สถานที่: ${item['company_name']}"),
                        Text("💼 ตำแหน่ง: ${item['position']}"),
                        const SizedBox(height: 15),
                        
                        // ปุ่มเพื่อกดเข้าไปดูรายละเอียดทั้งหมด
                        SizedBox(
                          width: double.infinity,
                          child: ElevatedButton.icon(
                            onPressed: () async {
                              // เมื่อกลับมาจากหน้า Detail ถ้ามีการอนุมัติ ให้รีเฟรชรายการ
                              final bool? shouldRefresh = await Navigator.push(
                                context,
                                MaterialPageRoute(builder: (context) => ApprovalDetailScreen(studentData: item)),
                              );
                              if (shouldRefresh == true) {
                                _fetchPendingRequests();
                              }
                            },
                            icon: const Icon(Icons.search, color: Colors.white),
                            label: const Text("ตรวจสอบรายละเอียด", style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
                            style: ElevatedButton.styleFrom(backgroundColor: Colors.blue.shade700, padding: const EdgeInsets.symmetric(vertical: 12)),
                          ),
                        ),
                      ],
                    ),
                  ),
                );
              },
            ),
    );
  }
}