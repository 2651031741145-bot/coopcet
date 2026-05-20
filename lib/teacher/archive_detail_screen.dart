import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'dart:convert';
import 'package:url_launcher/url_launcher.dart';

class ArchiveDetailScreen extends StatefulWidget {
  final Map<String, dynamic> studentData;

  const ArchiveDetailScreen({Key? key, required this.studentData}) : super(key: key);

  @override
  State<ArchiveDetailScreen> createState() => _ArchiveDetailScreenState();
}

class _ArchiveDetailScreenState extends State<ArchiveDetailScreen> {
  List<dynamic> _logs = [];
  Map<String, List<dynamic>> _groupedLogs = {}; 
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _fetchLogs();
  }

  Future<void> _fetchLogs() async {
    try {
      final response = await http.get(
        Uri.parse('https://student.cet.rmutr.ac.th/coopcet/internship/app/get_internship_logs.php?internship_id=${widget.studentData['internship_id']}'),
      );
      final data = jsonDecode(response.body);
      
      if (data['success'] && data['data'] != null) {
        _logs = data['data'];
      } else {
        _logs = [];
      }
      
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

  // 🚨 ใส่ระบบ Fail-Safe กันแอปค้าง กรณีวันที่พัง 🚨
  void _groupLogsByMonth() {
    _groupedLogs.clear();
    for (var log in _logs) {
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

  Future<void> _navigateToLocation(String? lat, String? lng) async {
    if (lat == null || lng == null || lat.isEmpty || lng.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text("ไม่มีข้อมูลพิกัดสำหรับสถานที่นี้"), backgroundColor: Colors.orange));
      return;
    }
    final String googleMapsUrl = "https://www.google.com/maps/search/?api=1&query=$lat,$lng";
    final Uri uri = Uri.parse(googleMapsUrl);
    if (await canLaunchUrl(uri)) {
      await launchUrl(uri, mode: LaunchMode.externalApplication);
    } else {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('ไม่สามารถเปิด Google Maps ได้')));
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

  @override
  Widget build(BuildContext context) {
    final s = widget.studentData;
    String benefitsText = s['has_benefits'] == 'yes' 
        ? "มี: ${s['benefit_details'] ?? 'ไม่ได้ระบุรายละเอียด'}" 
        : "ไม่มีสวัสดิการ";

    // 💡 เช็คว่ามีประวัติการย้ายสถานที่หรือไม่
    String? oldCompany = s['old_company_name'];
    String? relocateReason = s['relocate_reason'];
    bool hasRelocated = oldCompany != null && oldCompany.toString().trim().isNotEmpty;

    // 💡 เช็คว่ามีข้อมูลการประเมินหรือไม่
    bool hasEvaluation = s['eval_id'] != null;

    return Scaffold(
      backgroundColor: Colors.grey[50],
      appBar: AppBar(
        title: const Text("รายละเอียดการฝึกงาน", style: TextStyle(fontWeight: FontWeight.bold)),
        backgroundColor: Colors.indigo.shade800,
        foregroundColor: Colors.white,
        elevation: 0,
      ),
      body: _isLoading 
        ? const Center(child: CircularProgressIndicator(color: Colors.indigo)) 
        : SingleChildScrollView(
            padding: const EdgeInsets.all(20),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                _buildSectionHeader(" ข้อมูลนักศึกษา"),
                _buildInfoCard([
                  _infoTile(Icons.badge, "รหัสประจำตัว:", s['student_id']?.toString() ?? "-"),
                  _infoTile(Icons.person, "ชื่อ-นามสกุล:", s['full_name']?.toString() ?? "-"),
                  _infoTile(Icons.calendar_today, "ปีที่ออกฝึกงาน:", s['internship_year']?.toString() ?? "-"),
                ]),
                const SizedBox(height: 25),
                
                _buildSectionHeader(" รายละเอียดสถานที่ฝึกงาน"),
                _buildInfoCard([
                  // เปลี่ยนข้อความเป็น "สถานที่ปัจจุบัน" ถ้าเคยย้าย
                  _infoTile(Icons.business, hasRelocated ? "สถานที่ปัจจุบัน:" : "สถานที่:", s['company_name']?.toString() ?? "-"),
                  _infoTile(Icons.location_on, "ที่อยู่:", s['address']?.toString() ?? "-"),
                  Padding(
                    padding: const EdgeInsets.symmetric(vertical: 10),
                    child: SizedBox(
                      width: double.infinity,
                      child: OutlinedButton.icon(
                        onPressed: () => _navigateToLocation(s['latitude']?.toString(), s['longitude']?.toString()),
                        icon: const Icon(Icons.navigation_rounded, color: Colors.blue, size: 20),
                        label: const Text("เปิดนำทางด้วย Google Maps", style: TextStyle(color: Colors.blue, fontWeight: FontWeight.bold)),
                        style: OutlinedButton.styleFrom(side: BorderSide(color: Colors.blue.shade300, width: 1.5), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)), padding: const EdgeInsets.symmetric(vertical: 12)),
                      ),
                    ),
                  ),
                  
                  // 🚨 กล่องแสดงประวัติการย้าย (จะโผล่มาเฉพาะคนที่เคยย้าย) 🚨
                  if (hasRelocated) ...[
                    Container(
                      margin: const EdgeInsets.only(bottom: 15, top: 5),
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
                          const Divider(color: Colors.white),
                          Text("สถานที่เดิม: $oldCompany", style: TextStyle(fontSize: 13, color: Colors.grey.shade800)),
                          const SizedBox(height: 4),
                          Text("เหตุผลที่ย้าย: ${relocateReason ?? '-'}", style: TextStyle(fontSize: 13, color: Colors.grey.shade800)),
                        ],
                      ),
                    ),
                  ],

                  _infoTile(Icons.work, "ตำแหน่งที่ฝึกงาน:", s['position']?.toString() ?? "-"),
                  _infoTile(Icons.group, "จำนวนนักศึกษาที่รับ:", "${s['student_count'] ?? '-'} คน"),
                  _infoTile(Icons.person_pin, "อาจารย์ที่ไปนิเทศ:", s['supervisor_name']?.toString() ?? "-"),
                ]),
                const SizedBox(height: 25),
                
                _buildSectionHeader(" ข้อมูลเพิ่มเติม"),
                _buildInfoCard([
                  _infoTile(Icons.card_giftcard, "สวัสดิการ:", benefitsText),
                  _infoTile(Icons.public, "การเผยแพร่โปรเจกต์:", s['can_publish'] == 'yes' ? "ยินยอมให้เผยแพร่" : "ไม่อนุญาต"),
                  const Divider(height: 30),
                  SizedBox(
                    width: double.infinity,
                    child: ElevatedButton.icon(
                      onPressed: () => _openPdf(s['project_file_path']),
                      icon: const Icon(Icons.picture_as_pdf, color: Colors.white),
                      label: const Text("เปิดดูเล่มโปรเจกต์สหกิจ (PDF)", style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 16)),
                      style: ElevatedButton.styleFrom(backgroundColor: Colors.redAccent.shade700, padding: const EdgeInsets.symmetric(vertical: 15), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10))),
                    ),
                  ),
                ]),
                const SizedBox(height: 25),

                // 🚨 เพิ่ม Section ใหม่: ผลการประเมิน 🚨
                _buildSectionHeader(" ผลการประเมินจากสถานประกอบการ"),
                if (hasEvaluation) 
                  _buildEvaluationCard(s)
                else
                  Card(
                    elevation: 0,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(15), side: BorderSide(color: Colors.grey.shade300, width: 1)),
                    child: const Padding(
                      padding: EdgeInsets.all(20),
                      child: Center(
                        child: Text("ยังไม่มีผลการประเมิน", style: TextStyle(color: Colors.grey, fontWeight: FontWeight.bold)),
                      ),
                    ),
                  ),
                const SizedBox(height: 25),
                
                _buildSectionHeader(" ประวัติบันทึกงานรายวัน (${_logs.length} วัน)"),
                const SizedBox(height: 10),
                
                _groupedLogs.isEmpty 
                  ? const Center(child: Padding(padding: EdgeInsets.all(20), child: Text("ไม่พบข้อมูลการบันทึกงานที่สมบูรณ์", style: TextStyle(color: Colors.grey))))
                  : ListView.builder(
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
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12), side: BorderSide(color: Colors.indigo.shade100)),
                          child: ExpansionTile(
                            title: Text(monthYearKey, style: TextStyle(fontWeight: FontWeight.bold, color: Colors.indigo.shade800, fontSize: 16)),
                            subtitle: Text("บันทึกทั้งหมด ${logsInMonth.length} วัน  •  รวม $formattedTotalHours ชั่วโมง", style: TextStyle(color: Colors.grey.shade600, fontSize: 13, fontWeight: FontWeight.w500)),
                            collapsedIconColor: Colors.indigo,
                            iconColor: Colors.indigo,
                            childrenPadding: const EdgeInsets.only(bottom: 10),
                            children: logsInMonth.map((log) => _buildLogCard(log)).toList(),
                          ),
                        );
                      },
                    ),
                const SizedBox(height: 40),
              ],
            ),
          ),
    );
  }

  // Helper Methods สำหรับ UI
  Widget _buildSectionHeader(String title) => Padding(padding: const EdgeInsets.only(left: 5, bottom: 10), child: Text(title, style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: Colors.indigo.shade900)));
  
  Widget _buildInfoCard(List<Widget> children) => Card(elevation: 0, shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(15), side: BorderSide(color: Colors.grey.shade200, width: 1)), child: Padding(padding: const EdgeInsets.all(20), child: Column(children: children)));
  
  Widget _infoTile(IconData icon, String label, String value) => Padding(padding: const EdgeInsets.only(bottom: 12), child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [Icon(icon, size: 20, color: Colors.indigo.shade400), const SizedBox(width: 12), Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(label, style: TextStyle(color: Colors.grey.shade600, fontSize: 13, fontWeight: FontWeight.bold)), const SizedBox(height: 2), SizedBox(width: MediaQuery.of(context).size.width * 0.6, child: Text(value, style: const TextStyle(fontSize: 15, color: Colors.black87)))])]));

  // 🚨 Widget สำหรับแสดงข้อมูลประเมิน 🚨
  Widget _buildEvaluationCard(Map<String, dynamic> s) {
    return Card(
      elevation: 0,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(15), side: BorderSide(color: Colors.teal.shade300, width: 1.5)),
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text("คะแนนรวม", style: TextStyle(fontSize: 13, color: Colors.grey.shade600, fontWeight: FontWeight.bold)),
                    const SizedBox(height: 4),
                    Text("${s['total_score']} / 100", style: TextStyle(fontSize: 22, fontWeight: FontWeight.bold, color: Colors.teal.shade700)),
                  ],
                ),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                  decoration: BoxDecoration(color: Colors.teal.shade50, borderRadius: BorderRadius.circular(12), border: Border.all(color: Colors.teal.shade200)),
                  child: Row(
                    children: [
                      Icon(Icons.grade, color: Colors.teal.shade700, size: 18),
                      const SizedBox(width: 5),
                      Text("เกรด: ${s['overall_grade'] ?? '-'}", style: TextStyle(fontWeight: FontWeight.bold, color: Colors.teal.shade900, fontSize: 16)),
                    ],
                  ),
                )
              ],
            ),
            const Divider(height: 30),
            _evalTextRow(Icons.check_circle_outline, "รับเข้าทำงานต่อเนื่อง:", s['hire_decision']?.toString() ?? "-", Colors.green.shade700),
            const SizedBox(height: 12),
            _evalTextRow(Icons.thumb_up_alt_outlined, "จุดเด่นของนักศึกษา:", s['strengths']?.toString() ?? "-", Colors.blue.shade700),
            const SizedBox(height: 12),
            _evalTextRow(Icons.trending_up, "ข้อควรปรับปรุง:", s['improvements']?.toString() ?? "-", Colors.orange.shade700),
            const SizedBox(height: 12),
            _evalTextRow(Icons.comment_outlined, "ข้อคิดเห็นเพิ่มเติม:", s['comments']?.toString() ?? "-", Colors.grey.shade700),
          ],
        ),
      ),
    );
  }

  Widget _evalTextRow(IconData icon, String label, String value, Color iconColor) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(icon, size: 18, color: iconColor),
        const SizedBox(width: 8),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(label, style: TextStyle(fontWeight: FontWeight.bold, color: Colors.grey.shade700, fontSize: 13)),
              const SizedBox(height: 2),
              Text(value, style: const TextStyle(color: Colors.black87, fontSize: 14)),
            ],
          ),
        ),
      ],
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
}