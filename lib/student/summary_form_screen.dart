import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'dart:convert';
import 'dart:io';
import 'package:file_picker/file_picker.dart';
import 'package:internship/student/homestudent.dart';

class SummaryFormScreen extends StatefulWidget {
  final String internshipId;
  final String studentId;

  const SummaryFormScreen(
      {Key? key, required this.internshipId, required this.studentId})
      : super(key: key);

  @override
  State<SummaryFormScreen> createState() => _SummaryFormScreenState();
}

class _SummaryFormScreenState extends State<SummaryFormScreen> {
  final _formKey = GlobalKey<FormState>();

  // ตัวแปรเก็บข้อมูล Read-only ที่เพิ่มชื่อและที่อยู่
  String _displayStudentId = "";
  String _displayStudentName = "";
  String _displayCompanyName = "กำลังโหลด...";
  String _displayCompanyAddress = "";

  String _hasBenefits = 'no';
  String _canPublish = 'yes';
  File? _projectFile;

  final _benefitDetailsController = TextEditingController();
  final _positionController = TextEditingController();
  final _studentCountController = TextEditingController();
  final _internshipYearController = TextEditingController();

  List<dynamic> _teachers = [];
  String? _selectedTeacher;

  bool _isLoading = true;
  bool _isSubmitting = false;

  @override
  void initState() {
    super.initState();
    _fetchInitialData();
  }

  Future<void> _fetchInitialData() async {
    try {
      // 1. ดึงข้อมูลบริษัท, ที่อยู่, และชื่อนักศึกษา
      final detailsRes = await http.get(Uri.parse(
          'https://student.cet.rmutr.ac.th/coopcet/internship/app/get_summary_details.php?internship_id=${widget.internshipId}'));
      // 2. ดึงรายชื่ออาจารย์นิเทศ
      final teachersRes = await http.get(Uri.parse(
          'https://student.cet.rmutr.ac.th/coopcet/internship/app/get_teachers.php'));

      final detailsData = jsonDecode(detailsRes.body);
      final teachersData = jsonDecode(teachersRes.body);

      if (mounted) {
        setState(() {
          if (detailsData['success']) {
            _displayStudentId = detailsData['data']['student_id'] ?? "";
            _displayStudentName =
                detailsData['data']['full_name'] ?? "ไม่ระบุชื่อ";
            _displayCompanyName = detailsData['data']['company_name'] ?? "";
            _displayCompanyAddress =
                detailsData['data']['address'] ?? "ไม่ระบุที่อยู่";
          }
          if (teachersData['success']) {
            _teachers = teachersData['data'];
          }
          _isLoading = false;
        });
      }
    } catch (e) {
      debugPrint("Fetch Data Error: $e");
    }
  }

  Future<void> _pickFile() async {
    FilePickerResult? result = await FilePicker.platform.pickFiles(
      type: FileType.custom,
      allowedExtensions: ['pdf'],
    );
    if (result != null) {
      setState(() => _projectFile = File(result.files.single.path!));
    }
  }

  Future<void> _submitData() async {
    if (!_formKey.currentState!.validate()) return;
    if (_projectFile == null) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
          content: Text("กรุณาแนบไฟล์เล่มโปรเจกต์ .pdf"),
          backgroundColor: Colors.orange));
      return;
    }

    setState(() => _isSubmitting = true);
    try {
      var request = http.MultipartRequest(
          'POST',
          Uri.parse(
              'https://student.cet.rmutr.ac.th/coopcet/internship/app/save_internship_summary.php'));

      request.fields['internship_id'] = widget.internshipId;
      request.fields['student_id'] = _displayStudentId;
      request.fields['internship_year'] = _internshipYearController.text.trim();
      request.fields['has_benefits'] = _hasBenefits;
      request.fields['benefit_details'] = _benefitDetailsController.text.trim();
      request.fields['position'] = _positionController.text.trim();
      request.fields['student_count'] = _studentCountController.text.trim();
      request.fields['can_publish'] = _canPublish;
      request.fields['supervisor_name'] = _selectedTeacher ?? "";

      request.files.add(await http.MultipartFile.fromPath(
          'project_file', _projectFile!.path));

      var streamedResponse = await request.send();
      var response = await http.Response.fromStream(streamedResponse);
      var data = jsonDecode(response.body);

      if (data['success']) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
            content: Text("ส่งแบบสรุปผลสำเร็จ (รออาจารย์อนุมัติ)"),
            backgroundColor: Colors.green));

        // เด้งกลับไปหน้า HomeStudent และลบประวัติหน้าเดิมออกให้หมด
        // เพื่อป้องกันเด็กกดปุ่ม Back กลับมากรอกฟอร์มซ้ำ
        Navigator.pushAndRemoveUntil(
          context,
          MaterialPageRoute(
              // เรียกหน้า HomeStudent เปล่าๆ ได้เลยครับ เพราะหน้าโฮมจะไปดึงข้อมูล Login เอง
              builder: (context) => const HomeStudent()),
          (route) => false, // ลบทุก Route ทิ้ง
        );
      } else {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(
            content: Text(data['message']), backgroundColor: Colors.red));
      }
    } catch (e) {
      debugPrint("Error: $e");
    } finally {
      if (mounted) setState(() => _isSubmitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_isLoading)
      return const Scaffold(body: Center(child: CircularProgressIndicator()));

    return Scaffold(
      appBar: AppBar(
          title: const Text("แบบสรุปผลการฝึกงาน"),
          backgroundColor: Colors.blue.shade800,
          foregroundColor: Colors.white),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(20),
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // ปรับให้รวมรหัส+ชื่อ และ ชื่อบริษัท+ที่อยู่ ไว้ในกล่องเดียวเพื่อความสวยงาม
              _buildReadOnlyField(
                  "ข้อมูลนักศึกษา", "$_displayStudentId\n$_displayStudentName"),
              _buildReadOnlyField("สถานที่ฝึกงาน",
                  "$_displayCompanyName\n$_displayCompanyAddress"),
              const Divider(height: 40),

              // --- เพิ่มช่องปีที่ออกฝึกงานตรงนี้ ---
              _buildLabel("ปีที่ออกฝึกงาน (พ.ศ.) *"),
              TextFormField(
                controller: _internshipYearController, 
                keyboardType: TextInputType.number,
                decoration: _inputStyle("เช่น 2566"), 
                validator: (v) => v!.isEmpty ? 'กรุณาระบุปีที่ออกฝึกงาน' : null
              ),

              const Text("1. สวัสดิการ",
                  style: TextStyle(fontWeight: FontWeight.bold)),
              Row(
                children: [
                  Expanded(
                      child: RadioListTile(
                          title: const Text("ไม่มี"),
                          value: 'no',
                          groupValue: _hasBenefits,
                          onChanged: (v) =>
                              setState(() => _hasBenefits = v.toString()))),
                  Expanded(
                      child: RadioListTile(
                          title: const Text("มี"),
                          value: 'yes',
                          groupValue: _hasBenefits,
                          onChanged: (v) =>
                              setState(() => _hasBenefits = v.toString()))),
                ],
              ),
              if (_hasBenefits == 'yes')
                TextFormField(
                    controller: _benefitDetailsController,
                    decoration: _inputStyle(
                        "รายละเอียด เช่น ได้เบี้ยเลี้ยงวันละ 300, มีค่าอาหาร"),
                    validator: (v) => (_hasBenefits == 'yes' && v!.isEmpty)
                        ? 'กรุณาระบุสวัสดิการ'
                        : null),

              const SizedBox(height: 25),
              _buildLabel("2. ตำแหน่งที่ฝึก *"),
              TextFormField(
                  controller: _positionController,
                  decoration: _inputStyle("เช่น Software Engineer"),
                  validator: (v) => v!.isEmpty ? 'ระบุตำแหน่ง' : null),

              const SizedBox(height: 25),
              _buildLabel("3. จำนวนนักศึกษาที่แผนกรับ (มีเพื่อนกี่คน) *"),
              TextFormField(
                  controller: _studentCountController,
                  keyboardType: TextInputType.number,
                  decoration: _inputStyle("จำนวนคน"),
                  validator: (v) => v!.isEmpty ? 'ระบุจำนวน' : null),

              const SizedBox(height: 25),
              _buildLabel("4. โปรเจกต์สามารถเผยแพร่ได้หรือไม่ *"),
              Row(
                children: [
                  Expanded(
                      child: RadioListTile(
                          title: const Text("ได้"),
                          value: 'yes',
                          groupValue: _canPublish,
                          onChanged: (v) =>
                              setState(() => _canPublish = v.toString()))),
                  Expanded(
                      child: RadioListTile(
                          title: const Text("ไม่ได้"),
                          value: 'no',
                          groupValue: _canPublish,
                          onChanged: (v) =>
                              setState(() => _canPublish = v.toString()))),
                ],
              ),

              const SizedBox(height: 25),
              _buildLabel("5. อาจารย์ที่ไปนิเทศ *"),
              DropdownButtonFormField<String>(
                value: _selectedTeacher,
                decoration: _inputStyle("เลือกอาจารย์นิเทศ"),
                isExpanded: true, // ป้องกันชื่ออาจารย์ยาวจนล้นจอ
                items: _teachers.map((teacher) {
                  return DropdownMenuItem<String>(
                    value: teacher['full_name'],
                    child: Text(teacher['full_name']),
                  );
                }).toList(),
                onChanged: (val) => setState(() => _selectedTeacher = val),
                validator: (v) => v == null ? 'กรุณาเลือกอาจารย์นิเทศ' : null,
              ),

              const SizedBox(height: 30),
              _buildLabel("6. ไฟล์เล่มโปรเจกต์ (.pdf) *"),
              SizedBox(
                width: double.infinity,
                child: OutlinedButton.icon(
                  onPressed: _pickFile,
                  icon: const Icon(Icons.upload_file),
                  label: Text(_projectFile == null
                      ? "เลือกไฟล์ PDF"
                      : "เลือกไฟล์แล้ว: ${_projectFile!.path.split('/').last}"),
                  style: OutlinedButton.styleFrom(
                      padding: const EdgeInsets.all(15)),
                ),
              ),

              const SizedBox(height: 50),
              SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  onPressed: _isSubmitting ? null : _submitData,
                  style: ElevatedButton.styleFrom(
                      backgroundColor: Colors.blue.shade800,
                      padding: const EdgeInsets.all(15),
                      shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(10))),
                  child: _isSubmitting
                      ? const CircularProgressIndicator(color: Colors.white)
                      : const Text("ส่งข้อมูลสรุปการฝึกงาน",
                          style: TextStyle(
                              color: Colors.white,
                              fontSize: 18,
                              fontWeight: FontWeight.bold)),
                ),
              )
            ],
          ),
        ),
      ),
    );
  }

  // ปรับให้ TextFormField รองรับหลายบรรทัดได้ (maxLines: null) เพื่อแสดงชื่อและที่อยู่ครบถ้วน
  Widget _buildReadOnlyField(String label, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 15),
      child: TextFormField(
          initialValue: value,
          key: Key(value),
          readOnly: true,
          maxLines: null, // อนุญาตให้ขึ้นบรรทัดใหม่ได้
          decoration: InputDecoration(
              labelText: label,
              filled: true,
              fillColor: Colors.grey.shade100,
              border: const OutlineInputBorder())),
    );
  }

  Widget _buildLabel(String text) => Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Text(text, style: const TextStyle(fontWeight: FontWeight.bold)));
  InputDecoration _inputStyle(String hint) => InputDecoration(
      hintText: hint,
      border: const OutlineInputBorder(),
      contentPadding: const EdgeInsets.symmetric(horizontal: 15, vertical: 15));
}
