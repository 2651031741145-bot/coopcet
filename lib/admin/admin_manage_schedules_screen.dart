import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'dart:convert';
import 'dart:io';
import 'package:file_picker/file_picker.dart';
import 'package:url_launcher/url_launcher.dart';

class AdminManageSchedulesScreen extends StatefulWidget {
  final String adminId;
  const AdminManageSchedulesScreen({Key? key, required this.adminId}) : super(key: key);

  @override
  State<AdminManageSchedulesScreen> createState() => _AdminManageSchedulesScreenState();
}

class _AdminManageSchedulesScreenState extends State<AdminManageSchedulesScreen> {
  List<dynamic> _rounds = [];
  String? _selectedRoundId;
  String? _existingPdfPath; 
  File? _selectedPdfFile;
  bool _isLoading = true;
  bool _isUploading = false;

  final String apiUrl = 'https://student.cet.rmutr.ac.th/coopcet/internship/app/admin_manage_schedules.php';
  final String baseAppUrl = 'https://student.cet.rmutr.ac.th/coopcet/internship/app/';

  @override
  void initState() {
    super.initState();
    _fetchRounds();
  }

  Future<void> _fetchRounds() async {
    if (!mounted) return;
    setState(() => _isLoading = true);
    try {
      final response = await http.get(Uri.parse(apiUrl));
      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        if (data['success']) {
          if (!mounted) return;
          setState(() {
            _rounds = data['data'];
            
            bool roundStillExists = _rounds.any((r) => r['round_id'].toString() == _selectedRoundId);

            if (_rounds.isNotEmpty && (_selectedRoundId == null || !roundStillExists)) {
              _updateSelectedRound(_rounds[0]['round_id'].toString());
            } else if (_selectedRoundId != null && roundStillExists) {
              _updateSelectedRound(_selectedRoundId!);
            } else {
              _selectedRoundId = null;
              _existingPdfPath = null;
            }
          });
        }
      }
    } catch (e) {
      debugPrint("Fetch Error: $e");
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  void _updateSelectedRound(String roundId) {
    _selectedRoundId = roundId;
    _selectedPdfFile = null; 

    final round = _rounds.firstWhere((r) => r['round_id'].toString() == roundId, orElse: () => null);
    if (round != null && round['supervision_schedule_pdf'] != null && round['supervision_schedule_pdf'].toString().isNotEmpty) {
      _existingPdfPath = round['supervision_schedule_pdf'];
    } else {
      _existingPdfPath = null;
    }
  }

  Future<void> _viewExistingPdf() async {
    if (_existingPdfPath == null) return;
    String cleanPath = _existingPdfPath!.replaceAll("../internship/app/", "");
    final Uri url = Uri.parse("$baseAppUrl$cleanPath");

    if (!await launchUrl(url, mode: LaunchMode.externalApplication)) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('ไม่สามารถเปิดไฟล์ได้ 😅', style: TextStyle(fontFamily: 'Prompt'))));
      }
    }
  }

  Future<void> _pickPdfFile() async {
    FilePickerResult? result = await FilePicker.platform.pickFiles(
      type: FileType.custom,
      allowedExtensions: ['pdf'],
    );
    if (result != null && mounted) {
      setState(() {
        _selectedPdfFile = File(result.files.single.path!);
      });
    }
  }

  Future<void> _uploadSchedule() async {
    if (_selectedRoundId == null || _selectedPdfFile == null) return;
    setState(() => _isUploading = true);

    try {
      var request = http.MultipartRequest('POST', Uri.parse(apiUrl));
      request.fields['action'] = 'upload_pdf';
      request.fields['round_id'] = _selectedRoundId!;
      request.files.add(await http.MultipartFile.fromPath('pdf_file', _selectedPdfFile!.path));

      var response = await request.send();
      var responseData = await http.Response.fromStream(response);
      
      if (!mounted) return; // 🚨 ป้องกัน Context Error หลัง await

      var result = jsonDecode(responseData.body);

      if (result['success']) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(result['message'], style: const TextStyle(fontFamily: 'Prompt')), backgroundColor: Colors.green));
        _fetchRounds(); 
      } else {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(result['message'], style: const TextStyle(fontFamily: 'Prompt')), backgroundColor: Colors.red));
      }
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('เกิดข้อผิดพลาดในการเชื่อมต่อ', style: TextStyle(fontFamily: 'Prompt')), backgroundColor: Colors.red));
    } finally {
      if (mounted) setState(() => _isUploading = false);
    }
  }

  Future<void> _selectDate(BuildContext context, TextEditingController controller) async {
    DateTime? picked = await showDatePicker(
      context: context,
      initialDate: DateTime.now(), 
      firstDate: DateTime(2020),   
      lastDate: DateTime(2030),    
      builder: (context, child) {
        return Theme(
          data: Theme.of(context).copyWith(
            colorScheme: ColorScheme.light(primary: Colors.teal.shade700, onPrimary: Colors.white, onSurface: Colors.black),
          ),
          child: child!,
        );
      },
    );
    if (picked != null) {
      controller.text = "${picked.year}-${picked.month.toString().padLeft(2, '0')}-${picked.day.toString().padLeft(2, '0')}"; 
    }
  }

  void _showRoundDialog({bool isEdit = false}) {
    TextEditingController yearCtrl = TextEditingController();
    TextEditingController startCtrl = TextEditingController();
    TextEditingController endCtrl = TextEditingController();

    if (isEdit && _selectedRoundId != null) {
      final round = _rounds.firstWhere((r) => r['round_id'].toString() == _selectedRoundId);
      yearCtrl.text = round['academic_year'].toString();
      startCtrl.text = round['start_date'].toString();
      endCtrl.text = round['end_date'].toString();
    }

    showDialog(
      context: context,
      builder: (dialogContext) { // 🚨 เปลี่ยนชื่อเป็น dialogContext เพื่อไม่ให้ชนกับ context หลักของจอ
        return AlertDialog(
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(15)),
          title: Text(isEdit ? 'แก้ไขรอบการฝึกงาน ✏️' : 'เพิ่มรอบการฝึกงานใหม่ 📅', style: const TextStyle(fontWeight: FontWeight.bold, fontFamily: 'Prompt')),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                TextField(
                  controller: yearCtrl,
                  decoration: InputDecoration(labelText: 'ปีการศึกษา (เช่น 2568)', border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)), prefixIcon: const Icon(Icons.school)),
                  keyboardType: TextInputType.number,
                  style: const TextStyle(fontFamily: 'Prompt'),
                ),
                const SizedBox(height: 15),
                TextField(
                  controller: startCtrl,
                  readOnly: true, 
                  onTap: () => _selectDate(dialogContext, startCtrl), 
                  decoration: InputDecoration(labelText: 'วันเริ่ม', hintText: 'YYYY-MM-DD', border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)), prefixIcon: const Icon(Icons.calendar_today, color: Colors.teal)),
                  style: const TextStyle(fontFamily: 'Prompt'),
                ),
                const SizedBox(height: 15),
                TextField(
                  controller: endCtrl,
                  readOnly: true, 
                  onTap: () => _selectDate(dialogContext, endCtrl), 
                  decoration: InputDecoration(labelText: 'วันสิ้นสุด', hintText: 'YYYY-MM-DD', border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)), prefixIcon: const Icon(Icons.event_busy, color: Colors.orange)),
                  style: const TextStyle(fontFamily: 'Prompt'),
                ),
              ],
            ),
          ),
          actions: [
            TextButton(onPressed: () => Navigator.pop(dialogContext), child: const Text('ยกเลิก', style: TextStyle(color: Colors.grey, fontFamily: 'Prompt'))),
            ElevatedButton(
              style: ElevatedButton.styleFrom(backgroundColor: Colors.teal.shade700),
              onPressed: () async {
                if (yearCtrl.text.isEmpty || startCtrl.text.isEmpty || endCtrl.text.isEmpty) return;
                
                Navigator.pop(dialogContext); // 🚨 ใช้ dialogContext ในการปิด
                setState(() => _isLoading = true);

                try {
                  final response = await http.post(Uri.parse(apiUrl), body: {
                    'action': isEdit ? 'edit_round' : 'create_round',
                    'round_id': isEdit ? _selectedRoundId! : '', 
                    'academic_year': yearCtrl.text,
                    'start_date': startCtrl.text,
                    'end_date': endCtrl.text,
                    'admin_id': widget.adminId, 
                  });

                  if (!mounted) return; // 🚨 เช็คว่าจอหลักยังไม่โดนปิดไปไหน

                  final result = jsonDecode(response.body);
                  if (result['success']) {
                    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(result['message'], style: const TextStyle(fontFamily: 'Prompt')), backgroundColor: Colors.green));
                    await _fetchRounds(); 
                  }
                } catch (e) {
                   if (!mounted) return;
                   ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(isEdit ? 'แก้ไขไม่สำเร็จ' : 'เพิ่มรอบไม่สำเร็จ', style: const TextStyle(fontFamily: 'Prompt')), backgroundColor: Colors.red));
                } finally {
                  if (mounted && _isLoading) setState(() => _isLoading = false);
                }
              },
              child: Text(isEdit ? 'บันทึกการแก้ไข' : 'เพิ่มข้อมูล', style: const TextStyle(color: Colors.white, fontFamily: 'Prompt')),
            ),
          ],
        );
      },
    );
  }

  void _confirmDeleteRound() {
    if (_selectedRoundId == null) return;
    final round = _rounds.firstWhere((r) => r['round_id'].toString() == _selectedRoundId);

    showDialog(
      context: context,
      builder: (dialogContext) => AlertDialog( // 🚨 ใช้ dialogContext
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(15)),
        title: const Text('ลบรอบการฝึกงาน?', style: TextStyle(fontWeight: FontWeight.bold, color: Colors.red, fontFamily: 'Prompt')),
        content: Text('คุณแน่ใจหรือไม่ที่จะลบรอบปี ${round['academic_year']}?\n(หากมีไฟล์ PDF อยู่ ไฟล์จะถูกลบทิ้งถาวรด้วย)', style: const TextStyle(fontFamily: 'Prompt')),
        actions: [
          TextButton(onPressed: () => Navigator.pop(dialogContext), child: const Text('ยกเลิก', style: TextStyle(color: Colors.grey, fontFamily: 'Prompt'))),
          ElevatedButton(
            style: ElevatedButton.styleFrom(backgroundColor: Colors.red),
            onPressed: () async {
              Navigator.pop(dialogContext); // 🚨 ใช้ dialogContext
              setState(() => _isLoading = true);
              try {
                final response = await http.post(Uri.parse(apiUrl), body: {
                  'action': 'delete_round',
                  'round_id': _selectedRoundId!,
                });

                if (!mounted) return; // 🚨 ป้องกัน Context Error

                final result = jsonDecode(response.body);
                if (result['success']) {
                  ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(result['message'], style: const TextStyle(fontFamily: 'Prompt')), backgroundColor: Colors.green));
                  await _fetchRounds(); 
                }
              } catch (e) {
                if (!mounted) return;
                ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('เกิดข้อผิดพลาดในการลบ', style: TextStyle(fontFamily: 'Prompt')), backgroundColor: Colors.red));
              } finally {
                if (mounted) setState(() => _isLoading = false);
              }
            },
            child: const Text('ยืนยันลบ', style: TextStyle(color: Colors.white, fontFamily: 'Prompt')),
          ),
        ],
      ),
    );
  }

  Widget _buildPdfUploadBox() {
    if (_selectedPdfFile != null) {
      return Container(
        width: double.infinity,
        padding: const EdgeInsets.all(25),
        decoration: BoxDecoration(
          color: Colors.teal.shade50,
          border: Border.all(color: Colors.teal, width: 2, style: BorderStyle.solid),
          borderRadius: BorderRadius.circular(15),
        ),
        child: Column(
          children: [
            const Icon(Icons.check_circle, size: 50, color: Colors.teal),
            const SizedBox(height: 10),
            const Text('เตรียมอัปโหลดไฟล์:', style: TextStyle(fontWeight: FontWeight.bold, color: Colors.teal, fontFamily: 'Prompt')),
            Text(_selectedPdfFile!.path.split('/').last, textAlign: TextAlign.center, style: const TextStyle(fontFamily: 'Prompt')),
            const SizedBox(height: 15),
            TextButton.icon(
              onPressed: () => setState(() => _selectedPdfFile = null), 
              icon: const Icon(Icons.cancel, color: Colors.redAccent), 
              label: const Text('ยกเลิกการเลือก', style: TextStyle(color: Colors.redAccent, fontFamily: 'Prompt'))
            )
          ],
        ),
      );
    } 
    else if (_existingPdfPath != null) {
      return Container(
        width: double.infinity,
        padding: const EdgeInsets.all(25),
        decoration: BoxDecoration(
          color: Colors.white,
          border: Border.all(color: Colors.green.shade300, width: 2),
          borderRadius: BorderRadius.circular(15),
          boxShadow: [BoxShadow(color: Colors.green.withOpacity(0.1), blurRadius: 10)]
        ),
        child: Column(
          children: [
            Icon(Icons.picture_as_pdf, size: 50, color: Colors.green.shade600),
            const SizedBox(height: 10),
            const Text('มีไฟล์กำหนดการในระบบแล้ว', style: TextStyle(fontWeight: FontWeight.bold, color: Colors.green, fontSize: 16, fontFamily: 'Prompt')),
            const SizedBox(height: 20),
            Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                ElevatedButton.icon(
                  style: ElevatedButton.styleFrom(backgroundColor: Colors.green.shade600, foregroundColor: Colors.white, shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20))),
                  onPressed: _viewExistingPdf, 
                  icon: const Icon(Icons.visibility), 
                  label: const Text('เปิดดูไฟล์', style: TextStyle(fontFamily: 'Prompt'))
                ),
                const SizedBox(width: 10),
                OutlinedButton.icon(
                  style: OutlinedButton.styleFrom(foregroundColor: Colors.orange.shade700, side: BorderSide(color: Colors.orange.shade700), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20))),
                  onPressed: _pickPdfFile, 
                  icon: const Icon(Icons.edit), 
                  label: const Text('เปลี่ยนไฟล์', style: TextStyle(fontFamily: 'Prompt'))
                ),
              ],
            )
          ],
        ),
      );
    } 
    else {
      return InkWell(
        onTap: _pickPdfFile,
        child: Container(
          width: double.infinity,
          padding: const EdgeInsets.all(30),
          decoration: BoxDecoration(
            color: Colors.white,
            border: Border.all(color: Colors.grey.shade300, width: 2, style: BorderStyle.solid),
            borderRadius: BorderRadius.circular(15),
          ),
          child: Column(
            children: [
              Icon(Icons.upload_file, size: 50, color: Colors.grey.shade400),
              const SizedBox(height: 10),
              Text('แตะเพื่อเลือกไฟล์ PDF', style: TextStyle(color: Colors.grey.shade600, fontWeight: FontWeight.bold, fontFamily: 'Prompt'), textAlign: TextAlign.center),
            ],
          ),
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.grey[100],
      appBar: AppBar(
        title: const Text("จัดการกำหนดการนิเทศ", style: TextStyle(fontWeight: FontWeight.bold, fontFamily: 'Prompt')),
        backgroundColor: Colors.teal.shade800,
        foregroundColor: Colors.white,
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : Padding(
              padding: const EdgeInsets.all(20.0),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text('1. เลือกรอบการฝึกงาน', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: Colors.teal, fontFamily: 'Prompt')),
                  const SizedBox(height: 10),
                  Row(
                    children: [
                      Expanded(
                        child: Container(
                          padding: const EdgeInsets.symmetric(horizontal: 12),
                          decoration: BoxDecoration(
                            color: Colors.white,
                            border: Border.all(color: Colors.grey.shade300),
                            borderRadius: BorderRadius.circular(12),
                          ),
                          child: DropdownButtonHideUnderline(
                            child: DropdownButton<String>(
                              isExpanded: true,
                              value: _selectedRoundId,
                              hint: const Text('เลือกรอบฝึกงาน...', style: TextStyle(fontFamily: 'Prompt')),
                              items: _rounds.map((round) {
                                return DropdownMenuItem<String>(
                                  value: round['round_id'].toString(),
                                  child: Text('ปี ${round['academic_year']} (${round['start_date']} ถึง ${round['end_date']})', style: const TextStyle(fontFamily: 'Prompt', fontSize: 13)),
                                );
                              }).toList(),
                              onChanged: (value) {
                                if (value != null) {
                                  setState(() {
                                    _updateSelectedRound(value);
                                  });
                                }
                              },
                            ),
                          ),
                        ),
                      ),
                      const SizedBox(width: 10),
                      ElevatedButton(
                        onPressed: () => _showRoundDialog(isEdit: false),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: Colors.teal.shade100,
                          foregroundColor: Colors.teal.shade900,
                          padding: const EdgeInsets.all(16),
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                        ),
                        child: const Icon(Icons.add),
                      ),
                    ],
                  ),
                  
                  if (_selectedRoundId != null)
                    Padding(
                      padding: const EdgeInsets.only(top: 8.0),
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.end,
                        children: [
                          TextButton.icon(
                            onPressed: () => _showRoundDialog(isEdit: true),
                            icon: const Icon(Icons.edit, size: 16, color: Colors.blue),
                            label: const Text('แก้ไขข้อมูลรอบ', style: TextStyle(color: Colors.blue, fontFamily: 'Prompt', fontSize: 13)),
                          ),
                          const Text('|', style: TextStyle(color: Colors.grey)),
                          TextButton.icon(
                            onPressed: _confirmDeleteRound,
                            icon: const Icon(Icons.delete, size: 16, color: Colors.red),
                            label: const Text('ลบรอบฝึกงาน', style: TextStyle(color: Colors.red, fontFamily: 'Prompt', fontSize: 13)),
                          ),
                        ],
                      ),
                    ),

                  const SizedBox(height: 20),

                  const Text('2. เลือกไฟล์กำหนดการ (PDF)', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: Colors.teal, fontFamily: 'Prompt')),
                  const SizedBox(height: 10),
                  
                  _buildPdfUploadBox(),

                  const Spacer(),
                  
                  if (_selectedPdfFile != null)
                    SizedBox(
                      width: double.infinity,
                      height: 50,
                      child: ElevatedButton.icon(
                        style: ElevatedButton.styleFrom(
                          backgroundColor: Colors.teal.shade700,
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(25)),
                        ),
                        icon: _isUploading ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2)) : const Icon(Icons.cloud_upload, color: Colors.white),
                        label: Text(_isUploading ? 'กำลังอัปโหลด...' : 'บันทึกข้อมูลและอัปโหลดไฟล์', style: const TextStyle(fontSize: 16, color: Colors.white, fontWeight: FontWeight.bold, fontFamily: 'Prompt')),
                        onPressed: _isUploading ? null : _uploadSchedule,
                      ),
                    ),
                ],
              ),
            ),
    );
  }
}