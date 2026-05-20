import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'dart:convert';
import 'package:url_launcher/url_launcher.dart';

class ApprovalDetailScreen extends StatefulWidget {
  final Map<String, dynamic> studentData;

  const ApprovalDetailScreen({Key? key, required this.studentData}) : super(key: key);

  @override
  State<ApprovalDetailScreen> createState() => _ApprovalDetailScreenState();
}

class _ApprovalDetailScreenState extends State<ApprovalDetailScreen> {
  List<dynamic> _dailyLogs = [];
  Map<String, List<dynamic>> _groupedLogs = {}; 
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _fetchDailyLogs();
  }

  Future<void> _fetchDailyLogs() async {
    try {
      final response = await http.get(
        Uri.parse('https://student.cet.rmutr.ac.th/coopcet/internship/app/get_internship_logs.php?internship_id=${widget.studentData['internship_id']}'),
      );
      final data = jsonDecode(response.body);
      
      if (data['success'] && data['data'] != null) {
        _dailyLogs = data['data'];
      } else {
        _dailyLogs = [];
      }
      
      // เรียกใช้ฟังก์ชันจัดกลุ่ม
      _groupLogsByMonth(); 

    } catch (e) {
      debugPrint("Fetch Logs Error: $e");
    } finally {
      if (mounted) {
        setState(() {
          _isLoading = false;
        });
      }
    }
  }

  // 🚨 ระบบ Fail-Safe ให้ข้อมูลไม่หาย 🚨
  void _groupLogsByMonth() {
    _groupedLogs.clear();
    
    for (var log in _dailyLogs) {
      String groupKey = "บันทึกการทำงาน"; 

      try {
        String? dateStr = log['log_date']?.toString();
        
        if (dateStr != null && dateStr.trim().isNotEmpty) {
          DateTime? date;
          try {
            date = DateTime.parse(dateStr.trim());
          } catch (e) {
            groupKey = "เดือนที่ไม่ระบุชัดเจน";
          }

          if (date != null) {
            List<String> thaiMonths = [
              "มกราคม", "กุมภาพันธ์", "มีนาคม", "เมษายน", "พฤษภาคม", "มิถุนายน", 
              "กรกฎาคม", "สิงหาคม", "กันยายน", "ตุลาคม", "พฤศจิกายน", "ธันวาคม"
            ];
            String monthName = thaiMonths[date.month - 1];
            int thaiYear = date.year > 2500 ? date.year : date.year + 543;
            groupKey = "$monthName $thaiYear"; 
          }
        }
      } catch (e) {
        debugPrint("Group Logs Error: $e");
      }

      if (!_groupedLogs.containsKey(groupKey)) {
        _groupedLogs[groupKey] = [];
      }
      _groupedLogs[groupKey]!.add(log);
    }
  }

  Future<void> _openPdf(String? filePath) async {
    if (filePath == null || filePath.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text("ไม่พบไฟล์เล่มโปรเจกต์")));
      return;
    }
    final String fullUrl = "https://student.cet.rmutr.ac.th/coopcet/internship/app/$filePath";
    final Uri uri = Uri.parse(fullUrl);
    if (await canLaunchUrl(uri)) {
      await launchUrl(uri, mode: LaunchMode.externalApplication);
    } else {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text("ไม่สามารถเปิดไฟล์ได้")));
    }
  }

  Future<void> _navigateToLocation(String? lat, String? lng) async {
    if (lat == null || lng == null || lat.isEmpty || lng.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text("ไม่มีข้อมูลพิกัดสำหรับสถานที่นี้")));
      return;
    }
    final Uri uri = Uri.parse("https://www.google.com/maps/search/?api=1&query=$lat,$lng");
    if (await canLaunchUrl(uri)) {
      await launchUrl(uri, mode: LaunchMode.externalApplication);
    } else {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('ไม่สามารถเปิด Google Maps ได้')));
    }
  }

  Future<void> _reviewRequest(String action) async {
    showDialog(context: context, barrierDismissible: false, builder: (context) => const Center(child: CircularProgressIndicator()));
    try {
      final response = await http.post(
        Uri.parse('https://student.cet.rmutr.ac.th/coopcet/internship/app/review_internship.php'),
        body: {'internship_id': widget.studentData['internship_id'].toString(), 'action': action},
      );
      Navigator.pop(context); 
      final data = jsonDecode(response.body);
      
      if (data['success']) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(data['message']), backgroundColor: action == 'approve' ? Colors.green : Colors.orange));
        Navigator.pop(context, true); 
      } else {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(data['message']), backgroundColor: Colors.red));
      }
    } catch (e) {
      Navigator.pop(context);
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text("เกิดข้อผิดพลาดในการเชื่อมต่อ"), backgroundColor: Colors.red));
    }
  }

  void _confirmAction(String action) {
    bool isApprove = action == 'approve';
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(isApprove ? "อนุมัติจบการฝึกงาน" : "ส่งกลับให้แก้ไข", style: TextStyle(color: isApprove ? Colors.green : Colors.red, fontWeight: FontWeight.bold)),
        content: Text(isApprove ? "ยืนยันให้ '${widget.studentData['full_name']}' จบการฝึกงานใช่หรือไม่?" : "ส่งคำร้องกลับให้แก้ไขข้อมูลใหม่ใช่หรือไม่?"),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context), child: const Text("ยกเลิก", style: TextStyle(color: Colors.grey))),
          ElevatedButton(
            onPressed: () { Navigator.pop(context); _reviewRequest(action); },
            style: ElevatedButton.styleFrom(backgroundColor: isApprove ? Colors.green : Colors.red),
            child: const Text("ยืนยัน", style: TextStyle(color: Colors.white)),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.grey[100],
      appBar: AppBar(
        title: const Text("รายละเอียดคำขอจบฝึกงาน", style: TextStyle(fontSize: 18)),
        backgroundColor: Colors.orange.shade700,
        foregroundColor: Colors.white,
      ),
      body: _isLoading 
        ? const Center(child: CircularProgressIndicator(color: Colors.orange))
        : SingleChildScrollView(
            padding: const EdgeInsets.all(15),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                _buildSummarySection(),
                const SizedBox(height: 25),
                Text(" บันทึกการปฏิบัติงานรายวัน (${_dailyLogs.length} วัน)", style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: Colors.blueGrey.shade800)),
                const SizedBox(height: 10),
                _buildDailyLogsSection(), 
                const SizedBox(height: 80), 
              ],
            ),
          ),
      bottomSheet: Container(
        padding: const EdgeInsets.all(15),
        decoration: BoxDecoration(color: Colors.white, boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.1), blurRadius: 10, offset: const Offset(0, -3))]),
        child: Row(
          children: [
            Expanded(
              child: OutlinedButton.icon(
                onPressed: () => _confirmAction('reject'),
                icon: const Icon(Icons.edit_note, color: Colors.orange),
                label: const Text("ส่งกลับให้แก้ไข", style: TextStyle(color: Colors.orange, fontWeight: FontWeight.bold)),
                style: OutlinedButton.styleFrom(padding: const EdgeInsets.symmetric(vertical: 15), side: const BorderSide(color: Colors.orange, width: 2)),
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: ElevatedButton.icon(
                onPressed: () => _confirmAction('approve'),
                icon: const Icon(Icons.check, color: Colors.white),
                label: const Text("อนุมัติจบ", style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
                style: ElevatedButton.styleFrom(backgroundColor: Colors.green.shade600, padding: const EdgeInsets.symmetric(vertical: 15)),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildSummarySection() {
    final d = widget.studentData;
    String benefits = d['has_benefits'] == 'yes' ? d['benefit_details'] : "ไม่มีสวัสดิการ";
    String publish = d['can_publish'] == 'yes' ? "เผยแพร่ได้" : "ไม่อนุญาตให้เผยแพร่";

    // 💡 เช็คว่าเด็กคนนี้เคยย้ายสถานที่ฝึกงานหรือไม่
    String? oldCompany = d['old_company_name'];
    String? relocateReason = d['relocate_reason'];
    bool hasRelocated = oldCompany != null && oldCompany.toString().trim().isNotEmpty;

    return Card(
      elevation: 0,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(15), side: BorderSide(color: Colors.grey.shade300)),
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text("รหัส: ${d['student_id']}", style: TextStyle(color: Colors.orange.shade800, fontWeight: FontWeight.bold)),
            Text(d['full_name'], style: const TextStyle(fontSize: 20, fontWeight: FontWeight.bold)),
            const Divider(height: 25),
            
            // --- ข้อมูลสถานที่ปัจจุบัน ---
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Icon(Icons.business, size: 20, color: Colors.blue.shade700),
                const SizedBox(width: 8),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text("สถานที่ปัจจุบัน: ${d['company_name']}", style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
                      const SizedBox(height: 4),
                      Text("${d['address'] ?? '-'}", style: TextStyle(color: Colors.grey.shade700, fontSize: 13)),
                    ],
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            SizedBox(
              width: double.infinity,
              child: OutlinedButton.icon(
                onPressed: () => _navigateToLocation(d['latitude']?.toString(), d['longitude']?.toString()),
                icon: const Icon(Icons.navigation_rounded, color: Colors.blue, size: 18),
                label: const Text("เปิดนำทางด้วย Google Maps", style: TextStyle(color: Colors.blue)),
                style: OutlinedButton.styleFrom(side: BorderSide(color: Colors.blue.shade300)),
              ),
            ),
            
            // 🚨 กล่องแสดงประวัติการย้าย (จะแสดงเฉพาะคนที่มี oldCompany) 🚨
            if (hasRelocated) ...[
              const SizedBox(height: 15),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: Colors.red.shade50,
                  borderRadius: BorderRadius.circular(10),
                  border: Border.all(color: Colors.red.shade200),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Icon(Icons.history, color: Colors.red.shade700, size: 18),
                        const SizedBox(width: 5),
                        Text("เคยขอย้ายสถานที่ฝึกงาน", style: TextStyle(fontWeight: FontWeight.bold, color: Colors.red.shade800)),
                      ],
                    ),
                    const Divider(),
                    Text("สถานที่เดิม: $oldCompany", style: TextStyle(fontSize: 13, color: Colors.grey.shade800)),
                    const SizedBox(height: 4),
                    Text("เหตุผลที่ย้าย: ${relocateReason ?? '-'}", style: TextStyle(fontSize: 13, color: Colors.grey.shade800)),
                  ],
                ),
              ),
            ],

            const Divider(height: 30),

            _infoRow(Icons.calendar_month, "ปีที่ออกฝึกงาน:", d['internship_year']?.toString() ?? "-"),
            _infoRow(Icons.work, "ตำแหน่ง:", d['position']?.toString() ?? "-"),
            _infoRow(Icons.group, "จำนวนนักศึกษาที่รับ:", "${d['student_count']} คน"),
            _infoRow(Icons.card_giftcard, "สวัสดิการ:", benefits),
            _infoRow(Icons.public, "การเผยแพร่โปรเจกต์:", publish),
            _infoRow(Icons.person_outline, "อาจารย์นิเทศ:", d['supervisor_name']?.toString() ?? "-"),
            const SizedBox(height: 20),
            
            SizedBox(
              width: double.infinity,
              child: ElevatedButton.icon(
                onPressed: () => _openPdf(d['project_file_path']),
                icon: const Icon(Icons.picture_as_pdf, color: Colors.white),
                label: const Text("เปิดดูเล่มโปรเจกต์ (PDF)", style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
                style: ElevatedButton.styleFrom(backgroundColor: Colors.redAccent.shade700, padding: const EdgeInsets.symmetric(vertical: 12)),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildDailyLogsSection() {
    if (_groupedLogs.isEmpty) {
      return const Center(child: Padding(padding: EdgeInsets.all(20), child: Text("ไม่มีบันทึกการทำงาน", style: TextStyle(color: Colors.grey))));
    }
    
    return ListView.builder(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      itemCount: _groupedLogs.keys.length,
      itemBuilder: (context, index) {
        String monthYearKey = _groupedLogs.keys.elementAt(index);
        List<dynamic> logsInMonth = _groupedLogs[monthYearKey]!;

        double totalHours = 0;
        for (var log in logsInMonth) {
          bool isHoliday = log['is_holiday'] == "1" || log['is_holiday'] == 1 || log['is_holiday'] == true;
          if (!isHoliday) {
            totalHours += double.tryParse(log['hours_worked']?.toString() ?? '0') ?? 0.0;
          }
        }
        
        String formattedTotalHours = totalHours.truncateToDouble() == totalHours 
            ? totalHours.toInt().toString() 
            : totalHours.toString();

        return Card(
          margin: const EdgeInsets.only(bottom: 15),
          elevation: 0,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(12), 
            side: BorderSide(color: Colors.orange.shade200) 
          ),
          child: ExpansionTile(
            title: Text(monthYearKey, style: TextStyle(fontWeight: FontWeight.bold, color: Colors.orange.shade900, fontSize: 16)),
            subtitle: Text("บันทึกทั้งหมด ${logsInMonth.length} วัน  •  รวม $formattedTotalHours ชั่วโมง", style: TextStyle(color: Colors.grey.shade600, fontSize: 13, fontWeight: FontWeight.w500)),
            collapsedIconColor: Colors.orange.shade800,
            iconColor: Colors.orange.shade800,
            childrenPadding: const EdgeInsets.only(bottom: 10),
            children: logsInMonth.map((log) => _buildLogCard(log)).toList(),
          ),
        );
      },
    );
  }

  Widget _buildLogCard(dynamic log) {
    bool isHoliday = log['is_holiday'] == "1" || log['is_holiday'] == 1 || log['is_holiday'] == true;
    String hours = log['hours_worked']?.toString() ?? '0';

    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 15, vertical: 6),
      padding: const EdgeInsets.all(15),
      decoration: BoxDecoration(
        color: isHoliday ? Colors.orange.shade50 : Colors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: isHoliday ? Colors.orange.shade200 : Colors.green.shade200)
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text("วันที่: ${log['log_date']?.toString() ?? '-'}", style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15, color: isHoliday ? Colors.orange.shade800 : Colors.green.shade800)),
              if (isHoliday) 
                const Icon(Icons.beach_access, color: Colors.orange, size: 20)
              else
                Row(
                  mainAxisSize: MainAxisSize.min, 
                  children: [
                    const Icon(Icons.access_time, size: 16, color: Colors.grey),
                    const SizedBox(width: 4),
                    Text("$hours ชม.", style: const TextStyle(fontWeight: FontWeight.bold, color: Colors.grey)),
                  ],
                ),
            ],
          ),
          const Divider(),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(Icons.edit_note, size: 18, color: Colors.grey.shade500),
              const SizedBox(width: 8),
              Expanded(child: Text("งานที่ทำ: ${log['work_done']?.toString() ?? '-'}", style: const TextStyle(fontSize: 14))),
            ],
          ),
          if (!isHoliday && log['problem_found'] != null && log['problem_found'].toString().isNotEmpty) ...[
            const SizedBox(height: 8),
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Icon(Icons.warning_amber_rounded, size: 18, color: Colors.redAccent),
                const SizedBox(width: 8),
                Expanded(child: Text("ปัญหา: ${log['problem_found']}", style: const TextStyle(color: Colors.redAccent, fontSize: 14))),
              ],
            ),
            const SizedBox(height: 4),
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Icon(Icons.lightbulb_outline, size: 18, color: Colors.blueAccent),
                const SizedBox(width: 8),
                Expanded(child: Text("วิธีแก้: ${log['solution']}", style: const TextStyle(color: Colors.blueAccent, fontSize: 14))),
              ],
            ),
          ],
        ],
      ),
    );
  }

  Widget _infoRow(IconData icon, String label, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, size: 18, color: Colors.grey.shade600),
          const SizedBox(width: 8),
          Text("$label ", style: TextStyle(fontWeight: FontWeight.bold, color: Colors.grey.shade700)),
          Expanded(child: Text(value, style: const TextStyle(color: Colors.black87))),
        ],
      ),
    );
  }
}