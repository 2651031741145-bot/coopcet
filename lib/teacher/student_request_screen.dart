import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'dart:convert';
import 'package:intl/intl.dart'; // สำคัญ: ต้องใส่เพื่อจัดรูปแบบวันที่

class StudentRequestScreen extends StatefulWidget {
  const StudentRequestScreen({Key? key}) : super(key: key);

  @override
  State<StudentRequestScreen> createState() => _StudentRequestScreenState();
}

class _StudentRequestScreenState extends State<StudentRequestScreen> {
  List<dynamic> _requests = [];
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _fetchRequests();
  }

  // ดึงข้อมูลคำขอที่รออนุมัติ
  Future<void> _fetchRequests() async {
    setState(() => _isLoading = true);
    try {
      final response = await http.get(
        Uri.parse('https://student.cet.rmutr.ac.th/coopcet/internship/app/get_relocating_requests.php'),
      );

      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        if (data['success']) {
          setState(() {
            _requests = data['data'];
          });
        }
      }
    } catch (e) {
      debugPrint('Error: $e');
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('เกิดข้อผิดพลาดในการโหลดข้อมูล'), backgroundColor: Colors.red),
        );
      }
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  // อัปเดตสถานะ (อนุมัติ/ไม่อนุมัติ) พร้อมรับค่า custom date
  Future<void> _updateStatus(String internshipId, String status, {String remark = '', String customStart = '', String customEnd = ''}) async {
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (context) => const Center(child: CircularProgressIndicator(color: Colors.teal)),
    );

    try {
      final response = await http.post(
        Uri.parse('https://student.cet.rmutr.ac.th/coopcet/internship/app/update_relocation_status.php'),
        body: {
          'internship_id': internshipId,
          'status': status,
          'remark': remark,
          'custom_start_date': customStart,
          'custom_end_date': customEnd,
        },
      );

      Navigator.pop(context); // ปิด Loading

      final data = jsonDecode(response.body);
      if (data['success']) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(status == 'relocated' ? 'อนุมัติการย้ายสถานที่และกำหนดเวลาใหม่สำเร็จ' : 'ปฏิเสธคำร้องเรียบร้อยแล้ว'),
            backgroundColor: status == 'relocated' ? Colors.green : Colors.orange,
            behavior: SnackBarBehavior.floating,
          ),
        );
        _fetchRequests(); // รีเฟรชรายการใหม่
      } else {
        _showError(data['message'] ?? 'เกิดข้อผิดพลาด');
      }
    } catch (e) {
      Navigator.pop(context);
      _showError('การเชื่อมต่อล้มเหลว');
    }
  }

  // ==========================================
  // Dialog สำหรับกำหนดวันที่ฝึกงานใหม่ตอนอนุมัติ
  // ==========================================
  void _showApproveDialog(String internshipId, String studentName) {
    DateTime? selectedStartDate;
    DateTime? selectedEndDate;

    showDialog(
      context: context,
      builder: (context) => StatefulBuilder(
        builder: (context, setDialogState) {
          return AlertDialog(
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(15)),
            title: Column(
              children: [
                const Icon(Icons.calendar_month, color: Colors.teal, size: 40),
                const SizedBox(height: 10),
                const Text('กำหนดรอบฝึกงานใหม่', style: TextStyle(fontWeight: FontWeight.bold, color: Colors.teal, fontSize: 18)),
                Text('สำหรับ: $studentName', style: const TextStyle(fontSize: 14, color: Colors.grey)),
              ],
            ),
            content: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                const Text("กรุณากำหนดวันเริ่มและวันสิ้นสุดการฝึกงานใหม่ให้นักศึกษา", textAlign: TextAlign.center, style: TextStyle(fontSize: 13)),
                const SizedBox(height: 15),
                // เลือกวันเริ่มต้น
                ListTile(
                  shape: RoundedRectangleBorder(side: BorderSide(color: Colors.grey.shade300), borderRadius: BorderRadius.circular(8)),
                  leading: const Icon(Icons.play_circle_fill, color: Colors.green),
                  title: Text(selectedStartDate == null ? 'เลือกวันเริ่มฝึกงาน' : 'เริ่ม: ${DateFormat('yyyy-MM-dd').format(selectedStartDate!)}', style: const TextStyle(fontSize: 14)),
                  onTap: () async {
                    DateTime? picked = await showDatePicker(
                      context: context,
                      initialDate: DateTime.now(),
                      firstDate: DateTime(2020),
                      lastDate: DateTime(2030),
                    );
                    if (picked != null) setDialogState(() => selectedStartDate = picked);
                  },
                ),
                const SizedBox(height: 10),
                // เลือกวันสิ้นสุด
                ListTile(
                  shape: RoundedRectangleBorder(side: BorderSide(color: Colors.grey.shade300), borderRadius: BorderRadius.circular(8)),
                  leading: const Icon(Icons.stop_circle, color: Colors.red),
                  title: Text(selectedEndDate == null ? 'เลือกวันสิ้นสุดฝึกงาน' : 'สิ้นสุด: ${DateFormat('yyyy-MM-dd').format(selectedEndDate!)}', style: const TextStyle(fontSize: 14)),
                  onTap: () async {
                    DateTime? picked = await showDatePicker(
                      context: context,
                      initialDate: selectedStartDate ?? DateTime.now(),
                      firstDate: selectedStartDate ?? DateTime(2020),
                      lastDate: DateTime(2030),
                    );
                    if (picked != null) setDialogState(() => selectedEndDate = picked);
                  },
                ),
              ],
            ),
            actions: [
              TextButton(onPressed: () => Navigator.pop(context), child: const Text('ยกเลิก', style: TextStyle(color: Colors.grey))),
              ElevatedButton(
                style: ElevatedButton.styleFrom(backgroundColor: Colors.teal),
                onPressed: () {
                  if (selectedStartDate == null || selectedEndDate == null) {
                    ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('กรุณาเลือกวันที่ให้ครบถ้วน'), backgroundColor: Colors.red));
                    return;
                  }
                  Navigator.pop(context);
                  // เรียกฟังก์ชันอัปเดต พร้อมส่งวันที่กำหนดเองไป
                  _updateStatus(
                    internshipId, 
                    'relocated', 
                    customStart: DateFormat('yyyy-MM-dd').format(selectedStartDate!), 
                    customEnd: DateFormat('yyyy-MM-dd').format(selectedEndDate!)
                  );
                },
                child: const Text('ยืนยันอนุมัติ', style: TextStyle(color: Colors.white)),
              ),
            ],
          );
        }
      ),
    );
  }

  // Dialog สำหรับกรอกเหตุผลตอนไม่อนุมัติ
  void _showRejectDialog(String internshipId) {
    TextEditingController remarkController = TextEditingController();

    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(15)),
        title: const Row(
          children: [
            Icon(Icons.warning_amber_rounded, color: Colors.orange, size: 28),
            SizedBox(width: 8),
            Text('เหตุผลที่ไม่อนุมัติ', style: TextStyle(fontSize: 18)),
          ],
        ),
        content: TextField(
          controller: remarkController,
          maxLines: 3,
          decoration: InputDecoration(
            hintText: 'เช่น ข้อมูลไม่เพียงพอ, กรุณาติดต่ออาจารย์...',
            border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
            focusedBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(10),
              borderSide: const BorderSide(color: Colors.orange, width: 2),
            ),
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('ยกเลิก', style: TextStyle(color: Colors.grey)),
          ),
          ElevatedButton(
            style: ElevatedButton.styleFrom(
              backgroundColor: Colors.orange,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
            ),
            onPressed: () {
              if (remarkController.text.trim().isEmpty) {
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(content: Text('กรุณาระบุเหตุผลให้นักศึกษาทราบ'), backgroundColor: Colors.red),
                );
                return;
              }
              Navigator.pop(context);
              // หากไม่อนุมัติ ให้กลับไปสถานะ active เหมือนเดิม
              _updateStatus(internshipId, 'active', remark: remarkController.text.trim()); 
            },
            child: const Text('ยืนยันไม่อนุมัติ', style: TextStyle(color: Colors.white)),
          ),
        ],
      ),
    );
  }

  void _showError(String msg) {
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(msg), backgroundColor: Colors.red));
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.grey[100],
      appBar: AppBar(
        title: const Text('คำร้องขอย้ายสถานที่', style: TextStyle(fontWeight: FontWeight.bold)),
        backgroundColor: const Color(0xFF00897B),
        foregroundColor: Colors.white,
        elevation: 0,
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: Color(0xFF00897B)))
          : _requests.isEmpty
              ? _buildEmptyState()
              : RefreshIndicator(
                  color: const Color(0xFF00897B),
                  onRefresh: _fetchRequests,
                  child: ListView.builder(
                    padding: const EdgeInsets.all(16),
                    itemCount: _requests.length,
                    itemBuilder: (context, index) {
                      final req = _requests[index];
                      // ดึงเหตุผลมาแสดง (เผื่อเป็น null)
                      final String reason = req['relocate_reason'] ?? 'ไม่มีการระบุเหตุผล';

                      return Card(
                        elevation: 3,
                        margin: const EdgeInsets.only(bottom: 16),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(15)),
                        child: Padding(
                          padding: const EdgeInsets.all(16.0),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              // --- ส่วนหัว: ป้ายกำกับและข้อมูลนักศึกษา ---
                              Row(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  CircleAvatar(
                                    radius: 25,
                                    backgroundColor: Colors.orange.shade100,
                                    child: Icon(Icons.move_up, color: Colors.orange.shade700, size: 28),
                                  ),
                                  const SizedBox(width: 12),
                                  Expanded(
                                    child: Column(
                                      crossAxisAlignment: CrossAxisAlignment.start,
                                      children: [
                                        Container(
                                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                                          decoration: BoxDecoration(color: Colors.orange.shade50, borderRadius: BorderRadius.circular(5)),
                                          child: Text(
                                            'ขอย้ายสถานที่ฝึกงาน',
                                            style: TextStyle(fontSize: 12, color: Colors.orange.shade800, fontWeight: FontWeight.bold),
                                          ),
                                        ),
                                        const SizedBox(height: 5),
                                        Text(
                                          req['student_name'] ?? 'ชื่อนักศึกษา',
                                          style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
                                        ),
                                        Text(
                                          'รหัส: ${req['student_id'] ?? '-'}',
                                          style: TextStyle(fontSize: 14, color: Colors.grey[600]),
                                        ),
                                      ],
                                    ),
                                  ),
                                ],
                              ),
                              
                              const Padding(padding: EdgeInsets.symmetric(vertical: 12), child: Divider(height: 1)),
                              
                              // --- ส่วนข้อมูลสถานที่ปัจจุบัน ---
                              Row(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  const Icon(Icons.business, size: 20, color: Colors.grey),
                                  const SizedBox(width: 8),
                                  Expanded(
                                    child: Text(
                                      'สถานที่เดิม: ${req['company_name'] ?? '-'}',
                                      style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w500),
                                    ),
                                  ),
                                ],
                              ),
                              const SizedBox(height: 12),

                              // --- ส่วนแสดงเหตุผลที่ขอย้าย (Highlight) ---
                              Container(
                                padding: const EdgeInsets.all(12),
                                width: double.infinity,
                                decoration: BoxDecoration(
                                  color: Colors.red.shade50,
                                  borderRadius: BorderRadius.circular(8),
                                  border: Border.all(color: Colors.red.shade100),
                                ),
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Row(
                                      children: [
                                        Icon(Icons.info_outline, size: 18, color: Colors.red.shade700),
                                        const SizedBox(width: 5),
                                        Text('เหตุผลที่ขอย้าย:', style: TextStyle(fontWeight: FontWeight.bold, color: Colors.red.shade700)),
                                      ],
                                    ),
                                    const SizedBox(height: 5),
                                    Text(reason, style: TextStyle(color: Colors.grey.shade800)),
                                  ],
                                ),
                              ),
                              const SizedBox(height: 16),

                              // --- ส่วนปุ่มจัดการ ---
                              Row(
                                mainAxisAlignment: MainAxisAlignment.end,
                                children: [
                                  OutlinedButton.icon(
                                    style: OutlinedButton.styleFrom(
                                      foregroundColor: Colors.orange,
                                      side: const BorderSide(color: Colors.orange),
                                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8))
                                    ),
                                    onPressed: () => _showRejectDialog(req['internship_id'].toString()),
                                    icon: const Icon(Icons.close),
                                    label: const Text('ไม่อนุมัติ'),
                                  ),
                                  const SizedBox(width: 8),
                                  ElevatedButton.icon(
                                    style: ElevatedButton.styleFrom(
                                      backgroundColor: const Color(0xFF00897B),
                                      foregroundColor: Colors.white,
                                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                                    ),
                                    // เปลี่ยนมากดแล้วโชว์ Dialog ปฏิทินแทน
                                    onPressed: () => _showApproveDialog(req['internship_id'].toString(), req['student_name'] ?? 'นักศึกษา'),
                                    icon: const Icon(Icons.check),
                                    label: const Text('อนุมัติให้ย้าย'),
                                  ),
                                ],
                              )
                            ],
                          ),
                        ),
                      );
                    },
                  ),
                ),
    );
  }

  Widget _buildEmptyState() {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(Icons.check_circle_outline, size: 80, color: Colors.grey[300]),
          const SizedBox(height: 16),
          Text('ไม่มีคำร้องขอย้ายสถานที่', style: TextStyle(fontSize: 18, color: Colors.grey[600], fontWeight: FontWeight.bold)),
          Text('นักศึกษาทุกคนกำลังฝึกงานตามปกติ', style: TextStyle(fontSize: 14, color: Colors.grey[500])),
        ],
      ),
    );
  }
}