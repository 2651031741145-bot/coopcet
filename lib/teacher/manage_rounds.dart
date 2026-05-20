import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'dart:convert';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:intl/intl.dart';
import 'package:url_launcher/url_launcher.dart'; 

class ManageRounds extends StatefulWidget {
  const ManageRounds({Key? key}) : super(key: key);

  @override
  State<ManageRounds> createState() => _ManageRoundsState();
}

class _ManageRoundsState extends State<ManageRounds> with SingleTickerProviderStateMixin {
  late TabController _tabController;

  List<dynamic> _rounds = [];
  bool _isLoadingRounds = true;

  List<dynamic> _customStudents = [];
  bool _isLoadingCustoms = true;

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 2, vsync: this);
    _fetchRounds();
    _fetchCustomStudents(); 
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  // ==========================================
  // API Calls แท็บ 1
  // ==========================================
  Future<void> _fetchRounds() async {
    if (!mounted) return;
    setState(() => _isLoadingRounds = true);
    try {
      final response = await http.get(Uri.parse('https://student.cet.rmutr.ac.th/coopcet/internship/app/get_rounds.php'));
      if (response.statusCode == 200 && mounted) {
        setState(() {
          _rounds = jsonDecode(response.body);
          _isLoadingRounds = false;
        });
      }
    } catch (e) {
      if (mounted) setState(() => _isLoadingRounds = false);
    }
  }

  Future<void> _toggleRoundStatus(String roundId, String currentStatus) async {
    String newStatus = (currentStatus == 'open') ? 'closed' : 'open';
    _showLoadingDialog();
    try {
      final response = await http.post(
        Uri.parse('https://student.cet.rmutr.ac.th/coopcet/internship/app/update_round_status.php'),
        body: {'round_id': roundId, 'status': newStatus},
      );
      if (!mounted) return;
      Navigator.of(context, rootNavigator: true).pop();
      final data = jsonDecode(response.body);
      if (data['success']) {
        _fetchRounds();
        _showSnackBar(newStatus == 'open' ? 'เปิดรอบการฝึกงานแล้ว' : 'ปิดรอบการฝึกงานแล้ว', Colors.teal);
      }
    } catch (e) {
      if (!mounted) return;
      Navigator.of(context, rootNavigator: true).pop();
      _showSnackBar("เกิดข้อผิดพลาดในการเปลี่ยนสถานะ", Colors.red);
    }
  }

  Future<void> _saveRound(String year, String start, String end) async {
    _showLoadingDialog();
    SharedPreferences prefs = await SharedPreferences.getInstance();
    String? teacherId = prefs.getString('currentUser_username');
    try {
      final response = await http.post(
        Uri.parse('https://student.cet.rmutr.ac.th/coopcet/internship/app/add_round.php'),
        body: {'academic_year': year, 'start_date': start, 'end_date': end, 'created_by': teacherId ?? 'unknown'},
      );
      if (!mounted) return;
      Navigator.of(context, rootNavigator: true).pop();
      final data = jsonDecode(response.body);
      if (data['success']) {
        _showSnackBar("เพิ่มรอบการฝึกงานสำเร็จ", Colors.green);
        _fetchRounds();
      } else {
        _showErrorDialog(data['message']);
      }
    } catch (e) {
      if (!mounted) return;
      Navigator.of(context, rootNavigator: true).pop();
      _showErrorDialog("การเชื่อมต่อล้มเหลว");
    }
  }

  Future<void> _updateRound(String roundId, String year, String start, String end) async {
    _showLoadingDialog();
    try {
      final response = await http.post(
        Uri.parse('https://student.cet.rmutr.ac.th/coopcet/internship/app/update_internship_round.php'),
        body: {'round_id': roundId, 'academic_year': year, 'start_date': start, 'end_date': end},
      );
      if (!mounted) return;
      Navigator.of(context, rootNavigator: true).pop();
      final data = jsonDecode(response.body);
      if (data['success']) {
        _showSnackBar("แก้ไขข้อมูลเรียบร้อยแล้ว", Colors.blue);
        _fetchRounds();
      } else {
        _showErrorDialog(data['message']);
      }
    } catch (e) {
      if (!mounted) return;
      Navigator.of(context, rootNavigator: true).pop();
      _showErrorDialog("ไม่สามารถบันทึกการแก้ไขได้");
    }
  }

  Future<void> _deleteRound(String roundId) async {
    _showLoadingDialog();
    try {
      final response = await http.post(
        Uri.parse('https://student.cet.rmutr.ac.th/coopcet/internship/app/delete_internship_round.php'),
        body: {'round_id': roundId},
      );
      if (!mounted) return;
      Navigator.of(context, rootNavigator: true).pop();
      final data = jsonDecode(response.body);
      if (data['success']) {
        _showSnackBar("ลบรอบการฝึกงานสำเร็จ", Colors.orange);
        _fetchRounds();
      } else {
        _showErrorDialog(data['message']); 
      }
    } catch (e) {
      if (!mounted) return;
      Navigator.of(context, rootNavigator: true).pop();
      _showSnackBar("การเชื่อมต่อล้มเหลว", Colors.red);
    }
  }

  Future<void> _viewPdf(String pdfPath) async {
    String cleanPath = pdfPath.replaceAll("../internship/app/", "");
    final Uri url = Uri.parse("https://student.cet.rmutr.ac.th/coopcet/internship/app/$cleanPath");

    if (!await launchUrl(url, mode: LaunchMode.externalApplication)) {
      if (mounted) {
        _showSnackBar('ไม่สามารถเปิดไฟล์ได้ 😅', Colors.red);
      }
    }
  }

  // ==========================================
  // API Calls แท็บ 2 
  // ==========================================
  Future<void> _fetchCustomStudents() async {
    if (!mounted) return;
    setState(() => _isLoadingCustoms = true);
    try {
      final response = await http.get(Uri.parse('https://student.cet.rmutr.ac.th/coopcet/internship/app/get_custom_round_students.php'));
      if (response.statusCode == 200 && mounted) {
        final data = jsonDecode(response.body);
        if (data['success']) {
          setState(() {
            _customStudents = data['data'];
            _isLoadingCustoms = false;
          });
        } else {
          setState(() => _isLoadingCustoms = false);
        }
      }
    } catch (e) {
      if (mounted) setState(() => _isLoadingCustoms = false);
    }
  }

  Future<void> _updateCustomDates(String internshipId, String start, String end) async {
    _showLoadingDialog();
    try {
      final response = await http.post(
        Uri.parse('https://student.cet.rmutr.ac.th/coopcet/internship/app/update_custom_dates.php'),
        body: {'internship_id': internshipId, 'custom_start_date': start, 'custom_end_date': end},
      );
      
      if (!mounted) return; 
      Navigator.of(context, rootNavigator: true).pop(); 

      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        if (data['success']) {
          _showSnackBar("อัปเดตกำหนดการใหม่เรียบร้อยแล้ว", Colors.green);
          _fetchCustomStudents(); 
        } else {
          _showErrorDialog(data['message']);
        }
      } else {
        _showErrorDialog("ข้อผิดพลาดจากเซิร์ฟเวอร์: ${response.statusCode}");
      }
    } catch (e) {
      if (!mounted) return; 
      Navigator.of(context, rootNavigator: true).pop(); 
      debugPrint("Update API Error: $e");
      _showErrorDialog("การเชื่อมต่อล้มเหลว หรือ อ่านข้อมูล JSON ไม่ได้");
    }
  }

  // ==========================================
  // UI Dialogs
  // ==========================================
  void _showAddRoundDialog() {
    final formKey = GlobalKey<FormState>();
    final yearController = TextEditingController();
    DateTime? sDate; DateTime? eDate;

    showDialog(context: context, builder: (context) => StatefulBuilder(builder: (context, setDialogState) => AlertDialog(
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(15)),
      title: const Text("เพิ่มรอบฝึกงานใหม่", style: TextStyle(fontWeight: FontWeight.bold, color: Colors.teal)),
      content: Form(key: formKey, child: Column(mainAxisSize: MainAxisSize.min, children: [
        
        // 🚨 ส่วนที่แก้ใหม่: กล่องกรอกปีการศึกษาพร้อมคำอธิบาย
        TextFormField(
          controller: yearController, 
          keyboardType: TextInputType.number,
          decoration: InputDecoration(
            labelText: 'ปีการศึกษา (ตามรหัสนักศึกษา)', 
            hintText: 'เช่น 2565',
            helperText: '* ระบุปีที่เข้าศึกษา เช่น รหัส 265... ให้ใส่ 2565\n(ไม่ใช่ปี พ.ศ. ที่ออกฝึกงาน)',
            helperMaxLines: 2,
            helperStyle: TextStyle(color: Colors.grey[600], fontSize: 12),
            prefixIcon: const Icon(Icons.school),
            border: const OutlineInputBorder(), 
          ), 
          validator: (v) {
            if (v == null || v.isEmpty) return 'กรุณากรอกปี';
            if (v.length != 4) return 'กรุณาระบุปี 4 หลัก';
            return null;
          }
        ),
        
        const SizedBox(height: 15),
        ListTile(shape: RoundedRectangleBorder(side: BorderSide(color: Colors.grey.shade300), borderRadius: BorderRadius.circular(8)), leading: const Icon(Icons.date_range, color: Colors.green), title: Text(sDate == null ? 'วันเริ่ม' : DateFormat('yyyy-MM-dd').format(sDate!)), onTap: () async { 
          DateTime? p = await showDatePicker(context: context, initialDate: DateTime.now(), firstDate: DateTime(2020), lastDate: DateTime(2035)); 
          if (p != null) setDialogState(() => sDate = p); 
        }),
        const SizedBox(height: 10),
        ListTile(shape: RoundedRectangleBorder(side: BorderSide(color: Colors.grey.shade300), borderRadius: BorderRadius.circular(8)), leading: const Icon(Icons.event_available, color: Colors.red), title: Text(eDate == null ? 'วันจบ' : DateFormat('yyyy-MM-dd').format(eDate!)), onTap: () async { 
          DateTime baseDate = sDate ?? DateTime.now();
          DateTime safeInitial = eDate ?? baseDate;
          if (safeInitial.isBefore(baseDate)) safeInitial = baseDate;

          DateTime? p = await showDatePicker(context: context, initialDate: safeInitial, firstDate: baseDate, lastDate: DateTime(2035)); 
          if (p != null) setDialogState(() => eDate = p); 
        }),
      ])),
      actions: [
        TextButton(onPressed: () => Navigator.pop(context), child: const Text("ยกเลิก")),
        ElevatedButton(onPressed: () { if (formKey.currentState!.validate() && sDate != null && eDate != null) { Navigator.pop(context); _saveRound(yearController.text.trim(), DateFormat('yyyy-MM-dd').format(sDate!), DateFormat('yyyy-MM-dd').format(eDate!)); } }, style: ElevatedButton.styleFrom(backgroundColor: Colors.teal), child: const Text("บันทึก", style: TextStyle(color: Colors.white))),
      ],
    )));
  }

  void _showEditRoundDialog(Map<String, dynamic> round) {
    final formKey = GlobalKey<FormState>();
    final yearController = TextEditingController(text: round['academic_year'].toString()); 
    DateTime? sDate = DateTime.tryParse(round['start_date']);
    DateTime? eDate = DateTime.tryParse(round['end_date']);

    showDialog(context: context, builder: (context) => StatefulBuilder(builder: (context, setDialogState) => AlertDialog(
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(15)),
      title: const Text("แก้ไขรอบฝึกงาน", style: TextStyle(fontWeight: FontWeight.bold, color: Colors.blue)),
      content: Form(key: formKey, child: Column(mainAxisSize: MainAxisSize.min, children: [
        
        // 🚨 ส่วนที่แก้ใหม่: กล่องกรอกปีการศึกษาพร้อมคำอธิบาย (หน้าแก้ไข)
        TextFormField(
          controller: yearController, 
          keyboardType: TextInputType.number,
          decoration: InputDecoration(
            labelText: 'ปีการศึกษา (ตามรหัสนักศึกษา)', 
            hintText: 'เช่น 2565',
            helperText: '* ระบุปีที่เข้าศึกษา เช่น รหัส 265... ให้ใส่ 2565\n(ไม่ใช่ปี พ.ศ. ที่ออกฝึกงาน)',
            helperMaxLines: 2,
            helperStyle: TextStyle(color: Colors.grey[600], fontSize: 12),
            prefixIcon: const Icon(Icons.school),
            border: const OutlineInputBorder(), 
          ), 
          validator: (v) {
            if (v == null || v.isEmpty) return 'กรุณากรอกปี';
            if (v.length != 4) return 'กรุณาระบุปี 4 หลัก';
            return null;
          }
        ),
        
        const SizedBox(height: 15),
        ListTile(shape: RoundedRectangleBorder(side: BorderSide(color: Colors.grey.shade300), borderRadius: BorderRadius.circular(8)), leading: const Icon(Icons.date_range, color: Colors.green), title: Text('เริ่ม: ${DateFormat('yyyy-MM-dd').format(sDate!)}'), onTap: () async { 
          DateTime? p = await showDatePicker(context: context, initialDate: sDate!, firstDate: DateTime(2020), lastDate: DateTime(2035)); 
          if (p != null) setDialogState(() => sDate = p); 
        }),
        const SizedBox(height: 10),
        ListTile(shape: RoundedRectangleBorder(side: BorderSide(color: Colors.grey.shade300), borderRadius: BorderRadius.circular(8)), leading: const Icon(Icons.event_available, color: Colors.red), title: Text('จบ: ${DateFormat('yyyy-MM-dd').format(eDate!)}'), onTap: () async { 
          DateTime safeInitial = eDate!.isBefore(sDate!) ? sDate! : eDate!;
          DateTime? p = await showDatePicker(context: context, initialDate: safeInitial, firstDate: sDate!, lastDate: DateTime(2035)); 
          if (p != null) setDialogState(() => eDate = p); 
        }),
      ])),
      actions: [
        TextButton(onPressed: () => Navigator.pop(context), child: const Text("ยกเลิก")),
        ElevatedButton(onPressed: () { if (formKey.currentState!.validate()) { Navigator.pop(context); _updateRound(round['round_id'].toString(), yearController.text.trim(), DateFormat('yyyy-MM-dd').format(sDate!), DateFormat('yyyy-MM-dd').format(eDate!)); } }, style: ElevatedButton.styleFrom(backgroundColor: Colors.blue), child: const Text("บันทึกแก้ไข", style: TextStyle(color: Colors.white))),
      ],
    )));
  }

  void _confirmDelete(String roundId, String year) {
    showDialog(context: context, builder: (context) => AlertDialog(
      title: const Text("ยืนยันการลบ?"),
      content: Text("คุณต้องการลบรอบการฝึกงานปี $year ใช่หรือไม่?\nข้อมูลจะไม่สามารถกู้คืนได้"),
      actions: [
        TextButton(onPressed: () => Navigator.pop(context), child: const Text("ยกเลิก")),
        ElevatedButton(onPressed: () { Navigator.pop(context); _deleteRound(roundId); }, style: ElevatedButton.styleFrom(backgroundColor: Colors.red), child: const Text("ลบข้อมูล", style: TextStyle(color: Colors.white))),
      ],
    ));
  }

  void _showEditCustomDatesDialog(Map<String, dynamic> student) {
    DateTime? sDate = student['custom_start_date'] != null ? DateTime.tryParse(student['custom_start_date']) : null;
    DateTime? eDate = student['custom_end_date'] != null ? DateTime.tryParse(student['custom_end_date']) : null;

    showDialog(context: context, builder: (context) => StatefulBuilder(builder: (context, setDialogState) => AlertDialog(
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(15)),
      title: Text("กำหนดเวลาฝึกงานใหม่\n(${student['full_name']})", style: TextStyle(fontWeight: FontWeight.bold, color: Colors.orange.shade800, fontSize: 16)),
      content: Column(mainAxisSize: MainAxisSize.min, children: [
        const Text("การตั้งค่านี้จะเขียนทับวันเวลาในรอบปกติของนักศึกษาคนนี้เท่านั้น", style: TextStyle(fontSize: 12, color: Colors.grey)),
        const SizedBox(height: 15),
        ListTile(shape: RoundedRectangleBorder(side: BorderSide(color: Colors.grey.shade300), borderRadius: BorderRadius.circular(8)), leading: const Icon(Icons.date_range, color: Colors.green), title: Text(sDate == null ? 'เลือกวันเริ่มงาน' : DateFormat('yyyy-MM-dd').format(sDate!)), onTap: () async { 
          DateTime? p = await showDatePicker(context: context, initialDate: sDate ?? DateTime.now(), firstDate: DateTime(2020), lastDate: DateTime(2035)); 
          if (p != null) setDialogState(() => sDate = p); 
        }),
        const SizedBox(height: 10),
        ListTile(shape: RoundedRectangleBorder(side: BorderSide(color: Colors.grey.shade300), borderRadius: BorderRadius.circular(8)), leading: const Icon(Icons.event_available, color: Colors.red), title: Text(eDate == null ? 'เลือกวันสิ้นสุด' : DateFormat('yyyy-MM-dd').format(eDate!)), onTap: () async { 
          // 🚨 ดักจับ initialDate ป้องกัน Error
          DateTime baseDate = sDate ?? DateTime(2020);
          DateTime safeInitial = eDate ?? sDate ?? DateTime.now();
          if (safeInitial.isBefore(baseDate)) safeInitial = baseDate;

          DateTime? p = await showDatePicker(context: context, initialDate: safeInitial, firstDate: baseDate, lastDate: DateTime(2035)); 
          if (p != null) setDialogState(() => eDate = p); 
        }),
      ]),
      actions: [
        TextButton(onPressed: () => Navigator.pop(context), child: const Text("ยกเลิก")),
        ElevatedButton(
          onPressed: () { 
            if (sDate != null && eDate != null) { 
              Navigator.pop(context); 
              _updateCustomDates(student['internship_id'].toString(), DateFormat('yyyy-MM-dd').format(sDate!), DateFormat('yyyy-MM-dd').format(eDate!)); 
            } else {
              _showSnackBar("กรุณาเลือกวันที่ให้ครบถ้วน", Colors.orange);
            }
          }, 
          style: ElevatedButton.styleFrom(backgroundColor: Colors.orange.shade700), 
          child: const Text("บันทึก", style: TextStyle(color: Colors.white))
        ),
      ],
    )));
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.grey[50],
      appBar: AppBar(
        title: const Text('จัดการรอบการฝึกงาน', style: TextStyle(fontWeight: FontWeight.bold)), 
        backgroundColor: Colors.teal, 
        foregroundColor: Colors.white, 
        elevation: 0,
        bottom: TabBar(
          controller: _tabController,
          indicatorColor: Colors.white,
          indicatorWeight: 3,
          labelColor: Colors.white,
          unselectedLabelColor: Colors.teal.shade100,
          tabs: const [
            Tab(text: "รอบปกติ (ตามรหัสนศ.)"),
            Tab(text: "รอบเฉพาะบุคคล"),
          ],
        ),
      ),
      
      body: TabBarView(
        controller: _tabController,
        children: [
          // แท็บ 1
          _isLoadingRounds
            ? const Center(child: CircularProgressIndicator(color: Colors.teal))
            : RefreshIndicator(
                onRefresh: _fetchRounds,
                child: _rounds.isEmpty
                    ? _buildEmptyState("ยังไม่มีข้อมูลรอบการฝึกงาน")
                    : ListView.builder(
                        padding: const EdgeInsets.all(12),
                        itemCount: _rounds.length,
                        itemBuilder: (context, index) {
                          final round = _rounds[index];
                          bool isOpen = round['round_status'] == 'open';
                          bool hasPdf = round.containsKey('supervision_schedule_pdf') && 
                                        round['supervision_schedule_pdf'] != null && 
                                        round['supervision_schedule_pdf'].toString().isNotEmpty && 
                                        round['supervision_schedule_pdf'].toString() != 'null';

                          return Card(
                            elevation: 3,
                            margin: const EdgeInsets.only(bottom: 12),
                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(15)),
                            child: Opacity(
                              opacity: isOpen ? 1.0 : 0.6,
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  ListTile(
                                    contentPadding: EdgeInsets.only(left: 16, right: 16, top: 16, bottom: hasPdf ? 5 : 16),
                                    leading: CircleAvatar(backgroundColor: isOpen ? Colors.teal.shade50 : Colors.grey.shade200, child: Icon(Icons.date_range, color: isOpen ? Colors.teal : Colors.grey)),
                                    title: Text("รอบนักศึกษาปีการศึกษา ${round['academic_year']}", style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 18)),
                                    subtitle: Padding(
                                      padding: const EdgeInsets.only(top: 5),
                                      child: Text("เริ่ม: ${round['start_date']}\nจบ: ${round['end_date']}", style: const TextStyle(fontSize: 13, height: 1.4)),
                                    ),
                                    trailing: FittedBox(
                                      fit: BoxFit.scaleDown,
                                      child: Row(
                                        mainAxisSize: MainAxisSize.min,
                                        children: [
                                          IconButton(icon: const Icon(Icons.edit_note_rounded, color: Colors.blue, size: 28), onPressed: () => _showEditRoundDialog(round)),
                                          IconButton(icon: const Icon(Icons.delete_outline_rounded, color: Colors.redAccent, size: 28), onPressed: () => _confirmDelete(round['round_id'].toString(), round['academic_year'].toString())),
                                          Switch(value: isOpen, onChanged: (val) => _toggleRoundStatus(round['round_id'].toString(), round['round_status']), activeColor: Colors.teal),
                                        ],
                                      ),
                                    ),
                                  ),
                                  
                                  if (hasPdf)
                                    Padding(
                                      padding: const EdgeInsets.only(left: 72, bottom: 16, right: 16),
                                      child: InkWell(
                                        onTap: () => _viewPdf(round['supervision_schedule_pdf']),
                                        child: Container(
                                          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                                          decoration: BoxDecoration(
                                            color: Colors.teal.shade50,
                                            borderRadius: BorderRadius.circular(20),
                                            border: Border.all(color: Colors.teal.shade200),
                                          ),
                                          child: Row(
                                            mainAxisSize: MainAxisSize.min,
                                            children: [
                                              Icon(Icons.picture_as_pdf, size: 16, color: Colors.teal.shade700),
                                              const SizedBox(width: 5),
                                              Text(
                                                "ดูกำหนดการนิเทศ",
                                                style: TextStyle(fontSize: 12, color: Colors.teal.shade700, fontWeight: FontWeight.bold),
                                              ),
                                            ],
                                          ),
                                        ),
                                      ),
                                    ),
                                ],
                              ),
                            ),
                          );
                        },
                      ),
              ),

          // แท็บ 2
          _isLoadingCustoms
            ? const Center(child: CircularProgressIndicator(color: Colors.orange))
            : RefreshIndicator(
                onRefresh: _fetchCustomStudents,
                child: _customStudents.isEmpty
                    ? _buildEmptyState("ไม่มีนักศึกษาที่ต้องกำหนดรอบพิเศษ")
                    : ListView.builder(
                        padding: const EdgeInsets.all(12),
                        itemCount: _customStudents.length,
                        itemBuilder: (context, index) {
                          final student = _customStudents[index];
                          bool hasCustomDate = student['custom_start_date'] != null;

                          return Card(
                            elevation: 2,
                            margin: const EdgeInsets.only(bottom: 12),
                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(15), side: BorderSide(color: hasCustomDate ? Colors.orange.shade200 : Colors.grey.shade200)),
                            child: ListTile(
                              contentPadding: const EdgeInsets.all(16),
                              leading: CircleAvatar(backgroundColor: Colors.orange.shade50, child: const Icon(Icons.person, color: Colors.orange)),
                              title: Text(student['full_name'], style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
                              subtitle: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  const SizedBox(height: 5),
                                  Text("สถานที่: ${student['company_name']}", style: TextStyle(color: Colors.grey.shade700, fontSize: 13)),
                                  const SizedBox(height: 5),
                                  if (hasCustomDate)
                                    Text("เริ่ม: ${student['custom_start_date']}  ถึง  ${student['custom_end_date']}", style: TextStyle(color: Colors.orange.shade800, fontWeight: FontWeight.bold, fontSize: 12))
                                  else
                                    const Text("⚠️ ยังไม่ได้กำหนดเวลาใหม่", style: TextStyle(color: Colors.redAccent, fontSize: 12)),
                                ],
                              ),
                              trailing: OutlinedButton(
                                onPressed: () => _showEditCustomDatesDialog(student),
                                style: OutlinedButton.styleFrom(side: BorderSide(color: Colors.orange.shade400)),
                                child: Text(hasCustomDate ? "แก้ไข" : "ตั้งค่า", style: TextStyle(color: Colors.orange.shade800)),
                              ),
                            ),
                          );
                        },
                      ),
              ),
        ],
      ),

      floatingActionButton: _tabController.index == 0 
        ? FloatingActionButton.extended(
            onPressed: _showAddRoundDialog, 
            backgroundColor: Colors.teal, 
            icon: const Icon(Icons.add, color: Colors.white), 
            label: const Text("เพิ่มรอบฝึกงาน", style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold))
          )
        : null, 
    );
  }

  void _showLoadingDialog() {
    if (!mounted) return;
    showDialog(context: context, barrierDismissible: false, builder: (_) => const Center(child: CircularProgressIndicator(color: Colors.teal)));
  }

  void _showSnackBar(String msg, Color color) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(msg), backgroundColor: color, behavior: SnackBarBehavior.floating));
  }

  void _showErrorDialog(String msg) {
    if (!mounted) return;
    showDialog(context: context, builder: (_) => AlertDialog(title: const Text("ไม่สามารถทำรายการได้"), content: Text(msg), actions: [TextButton(onPressed: () => Navigator.pop(context), child: const Text("ตกลง"))]));
  }

  Widget _buildEmptyState(String msg) => Center(child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [Icon(Icons.calendar_today_outlined, size: 80, color: Colors.grey[300]), Text(msg, style: const TextStyle(color: Colors.grey))]));
}