import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:internship/student/summary_form_screen.dart'; // ตรวจสอบ path
import 'dart:convert';
import 'package:intl/intl.dart';
import 'package:table_calendar/table_calendar.dart';
import 'package:shared_preferences/shared_preferences.dart';

class DailyLogScreen extends StatefulWidget {
  final String studentId;
  const DailyLogScreen({Key? key, required this.studentId}) : super(key: key);

  @override
  State<DailyLogScreen> createState() => _DailyLogScreenState();
}

class _DailyLogScreenState extends State<DailyLogScreen> {
  final _formKey = GlobalKey<FormState>();
  final _workDoneController = TextEditingController();
  final _problemController = TextEditingController();
  final _solutionController = TextEditingController();
  final _hoursController = TextEditingController();

  DateTime? _startDate; 
  DateTime? _endDate;
  String _internshipId = "";
  
  Map<String, bool> _filledDatesStatus = {}; 
  bool _isHoliday = false; 
  
  DateTime _selectedDate = DateTime.now();
  DateTime _focusedDay = DateTime.now();
  DateTime _firstDay = DateTime.utc(2020, 1, 1);
  DateTime _lastDay = DateTime.utc(2030, 12, 31);
  
  bool _isLoading = true;
  bool _isSaving = false;
  bool _canEdit = false; 

  @override
  void initState() {
    super.initState();
    _fetchRoundInfo();
  }

  // 1. ดึงข้อมูลรอบการฝึกงาน
  Future<void> _fetchRoundInfo() async {
    try {
      // 🚨 1. ดึงปีการศึกษาของนักศึกษาที่ล็อกอินอยู่จาก SharedPreferences
      SharedPreferences prefs = await SharedPreferences.getInstance();
      String academicYear = prefs.getString('currentUser_academicYear') ?? '';

      // 🚨 2. แนบ &academic_year=$academicYear ไปกับ URL ด้วย
      final response = await http.get(
        Uri.parse('https://student.cet.rmutr.ac.th/coopcet/internship/app/get_internship_round_details.php?student_id=${widget.studentId}&academic_year=$academicYear'),
      ).timeout(const Duration(seconds: 10)); 
      
      final data = jsonDecode(response.body);

      if (data['success']) {
        DateTime start = DateTime.parse(data['data']['custom_start_date'] ?? data['data']['start_date']);
        DateTime end = DateTime.parse(data['data']['custom_end_date'] ?? data['data']['end_date']);
        
        if (mounted) {
          setState(() {
            _internshipId = data['data']['internship_id'].toString();
            _startDate = start;
            _endDate = end;
            
            // ขยายขอบเขตปฏิทินให้เลื่อนดูได้ 1 ปี ย้อนหลัง/ล่วงหน้า
            _firstDay = DateTime(start.year - 1, start.month, 1); 
            _lastDay = DateTime(end.year + 1, end.month, 31); 
            
            DateTime now = DateTime.now();
            _focusedDay = now.isBefore(start) ? start : (now.isAfter(end) ? end : now);
            _selectedDate = _focusedDay;
          });
        }
        
        await _fetchFilledDates(); 
        _validateAndFetchLog(_selectedDate); 
      } else {
        _handleError(data['message']);
      }
    } catch (e) {
      debugPrint("Fetch Round Error: $e");
      _handleError("การเชื่อมต่อล้มเหลว หรือ เซิร์ฟเวอร์ไม่ตอบสนอง");
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  // 2. ดึงประวัติการบันทึกเพื่อระบายสีปฏิทิน
  Future<void> _fetchFilledDates() async {
    try {
      // 🚨 เพิ่ม Timeout
      final res = await http.get(
        Uri.parse('https://student.cet.rmutr.ac.th/coopcet/internship/app/get_filled_logs_dates.php?internship_id=$_internshipId')
      ).timeout(const Duration(seconds: 10));
      
      final data = jsonDecode(res.body);
      if (data['success']) {
        Map<String, bool> temp = {};
        for (var item in data['filled_dates']) {
          temp[item['log_date']] = item['is_holiday'] == "1" || item['is_holiday'] == 1;
        }
        if (mounted) {
          setState(() {
            _filledDatesStatus = temp;
          });
        }
      }
    } catch (e) {
      debugPrint("Error fetching filled dates: $e");
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text("ไม่สามารถโหลดประวัติวันหยุดได้", style: TextStyle(color: Colors.white)), backgroundColor: Colors.orange));
    }
  }

  void _handleError(String msg) {
    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(msg), backgroundColor: Colors.red));
      // 🚨 แก้บั๊กตรงนี้: ถ้าโหลดพังตอนเปิดหน้าแรก ให้ปิดหน้าต่างกลับไปหน้าเดิม (ไม่ให้ติดแหง็กที่จอนี้)
      if (_isLoading) Navigator.pop(context); 
    }
  }

  // 3. จัดการตอนเลือกวันที่ในปฏิทิน
  void _validateAndFetchLog(DateTime date) {
    _workDoneController.clear();
    _problemController.clear();
    _solutionController.clear();
    _hoursController.clear();
    setState(() => _isHoliday = false);

    DateTime today = DateTime(DateTime.now().year, DateTime.now().month, DateTime.now().day);
    DateTime checkDate = DateTime(date.year, date.month, date.day);
    DateTime start = DateTime(_startDate!.year, _startDate!.month, _startDate!.day);
    DateTime end = DateTime(_endDate!.year, _endDate!.month, _endDate!.day);

    if (checkDate.isBefore(start) || checkDate.isAfter(end) || checkDate.isAfter(today)) {
      setState(() => _canEdit = false);
    } else {
      setState(() => _canEdit = true);
      _fetchDailyLog(date); 
    }
  }

  Future<void> _fetchDailyLog(DateTime date) async {
    String dateStr = DateFormat('yyyy-MM-dd').format(date);
    
    // 🚨 เพิ่มสถานะโหลดเพื่อโชว์ตอนดึงข้อมูลรายวัน (Optional แต่ช่วยให้รู้ว่าแอปกำลังคิดอยู่)
    setState(() => _isSaving = true); 

    try {
      // 🚨 เพิ่ม Timeout
      final response = await http.get(
        Uri.parse('https://student.cet.rmutr.ac.th/coopcet/internship/app/get_daily_log.php?internship_id=$_internshipId&log_date=$dateStr'),
      ).timeout(const Duration(seconds: 10));

      final data = jsonDecode(response.body);
      if (data['success'] && data['data'] != null && mounted) {
        setState(() {
          _isHoliday = data['data']['is_holiday'] == "1" || data['data']['is_holiday'] == 1;
          _workDoneController.text = data['data']['work_done'] ?? '';
          _problemController.text = data['data']['problem_found'] ?? '';
          _solutionController.text = data['data']['solution'] ?? '';
          _hoursController.text = data['data']['hours_worked']?.toString() ?? '';
        });
      }
    } catch (e) { 
      debugPrint("Error fetch daily log: $e"); 
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text("ดึงข้อมูลรายวันล้มเหลว กรุณาลองใหม่", style: TextStyle(color: Colors.white)), backgroundColor: Colors.orange));
    } finally {
      if (mounted) setState(() => _isSaving = false); // ปิดสถานะโหลด
    }
  }

  // 4. บันทึกข้อมูลลงฐานข้อมูล
  Future<void> _saveLog() async {
    if (!_isHoliday && !_formKey.currentState!.validate()) return;
    
    setState(() => _isSaving = true);
    String dateStr = DateFormat('yyyy-MM-dd').format(_selectedDate);

    try {
      // 🚨 เพิ่ม Timeout ให้ตอนเซฟด้วย ป้องกันกดเซฟแล้วปุ่มหมุนค้างตลอดกาล
      final response = await http.post(
        Uri.parse('https://student.cet.rmutr.ac.th/coopcet/internship/app/save_daily_log.php'),
        body: {
          'internship_id': _internshipId,
          'log_date': dateStr,
          'work_done': _isHoliday ? "วันหยุด / ลาพัก" : _workDoneController.text.trim(),
          'problem_found': _problemController.text.trim(),
          'solution': _solutionController.text.trim(),
          'hours_worked': _isHoliday ? "0" : _hoursController.text.trim(),
          'is_holiday': _isHoliday ? "1" : "0",
        },
      ).timeout(const Duration(seconds: 10));
      
      final res = jsonDecode(response.body);
      
      if (res['success']) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text("บันทึกข้อมูลเรียบร้อย"), backgroundColor: Colors.green));
          setState(() {
            _filledDatesStatus[dateStr] = _isHoliday;
          });
        }
      } else {
        if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(res['message']), backgroundColor: Colors.red));
      }
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text("การเชื่อมต่อล้มเหลว หรือเซิร์ฟเวอร์ช้าเกินไป"), backgroundColor: Colors.red));
    } finally {
      // 🚨 บังคับปลดล็อกปุ่มเซฟเสมอ
      if (mounted) setState(() => _isSaving = false);
    }
  }

  // 5. คำนวณหาวันที่ยังไม่ได้บันทึก
  List<String> _getMissingDates() {
    List<String> missing = [];
    if (_startDate == null || _endDate == null) return missing;
    
    DateTime today = DateTime(DateTime.now().year, DateTime.now().month, DateTime.now().day);
    DateTime limitDate = today.isBefore(_endDate!) ? today : _endDate!;

    for (DateTime d = _startDate!; !d.isAfter(limitDate); d = d.add(const Duration(days: 1))) {
      String dStr = DateFormat('yyyy-MM-dd').format(d);
      if (!_filledDatesStatus.containsKey(dStr)) {
        missing.add(dStr);
      }
    }
    return missing;
  }

  // 6. ส่วนแสดงสีปฏิทินตามสถานะ
  Widget? _buildCalendarCell(DateTime day, {bool isSelected = false, bool isToday = false}) {
    if (_startDate == null || _endDate == null) return null; // ดัก Error เพิ่มกรณีเน็ตพังตอนเปิดหน้าแรก

    DateTime checkDay = DateTime(day.year, day.month, day.day);
    DateTime start = DateTime(_startDate!.year, _startDate!.month, _startDate!.day);
    DateTime end = DateTime(_endDate!.year, _endDate!.month, _endDate!.day);
    DateTime today = DateTime(DateTime.now().year, DateTime.now().month, DateTime.now().day);

    if (checkDay.isBefore(start) || checkDay.isAfter(end)) return null;

    String dateStr = DateFormat('yyyy-MM-dd').format(checkDay);
    bool isFilled = _filledDatesStatus.containsKey(dateStr);
    bool isHoliday = _filledDatesStatus[dateStr] ?? false;
    bool isFuture = checkDay.isAfter(today);

    Color bgColor = Colors.grey.shade200;
    Color textColor = Colors.black87;

    if (isSelected) {
      bgColor = Colors.blue.shade900; 
      textColor = Colors.white;
    } else if (isFuture) {
      bgColor = Colors.grey.shade100; 
      textColor = Colors.grey;
    } else if (isFilled) {
      bgColor = isHoliday ? Colors.orange.shade400 : Colors.green.shade500;
      textColor = Colors.white;
    } else {
      bgColor = Colors.red.shade400; 
      textColor = Colors.white;
    }

    return Container(
      margin: const EdgeInsets.all(6),
      decoration: BoxDecoration(color: bgColor, shape: BoxShape.circle),
      alignment: Alignment.center,
      child: Text('${day.day}', style: TextStyle(color: textColor, fontWeight: FontWeight.bold, fontSize: 13)),
    );
  }

  @override
  Widget build(BuildContext context) {
    if (_isLoading) return const Scaffold(body: Center(child: CircularProgressIndicator(color: Colors.green)));

    return Scaffold(
      backgroundColor: Colors.grey[50],
      appBar: AppBar(title: const Text("บันทึกงานรายวัน", style: TextStyle(fontWeight: FontWeight.bold)), backgroundColor: Colors.green.shade700, foregroundColor: Colors.white, elevation: 0),
      body: SingleChildScrollView(
        child: Column(
          children: [
            // --- แถบอธิบายสี ---
            Container(
              color: Colors.white, padding: const EdgeInsets.symmetric(vertical: 10),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  _buildLegend(Colors.green.shade500, "วันทำงาน"),
                  const SizedBox(width: 12),
                  _buildLegend(Colors.orange.shade400, "วันหยุด"),
                  const SizedBox(width: 12),
                  _buildLegend(Colors.red.shade400, "ขาดบันทึก"),
                ],
              ),
            ),
            
            // --- ปฏิทิน ---
            Container(
              decoration: BoxDecoration(color: Colors.white, boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.05), blurRadius: 10)]),
              child: TableCalendar(
                firstDay: _firstDay, 
                lastDay: _lastDay, 
                focusedDay: _focusedDay,
                selectedDayPredicate: (day) => isSameDay(_selectedDate, day),
                onDaySelected: (selectedDay, focusedDay) {
                  // 🚨 ป้องกันการกดวันอื่นรัวๆ ระหว่างที่แอปกำลังเซฟหรือดึงข้อมูลอยู่
                  if (!isSameDay(_selectedDate, selectedDay) && !_isSaving) {
                    setState(() { 
                      _selectedDate = selectedDay; 
                      _focusedDay = focusedDay; 
                    });
                    _validateAndFetchLog(selectedDay);
                  }
                },
                calendarBuilders: CalendarBuilders(
                  defaultBuilder: (context, day, focusedDay) => _buildCalendarCell(day),
                  todayBuilder: (context, day, focusedDay) => _buildCalendarCell(day, isToday: true),
                  selectedBuilder: (context, day, focusedDay) => _buildCalendarCell(day, isSelected: true),
                ),
                headerStyle: const HeaderStyle(formatButtonVisible: false, titleCentered: true),
              ),
            ),
            
            const SizedBox(height: 15),
            
            // --- ฟอร์มบันทึกข้อมูล ---
            if (_canEdit)
              Container(
                margin: const EdgeInsets.all(15), padding: const EdgeInsets.all(20),
                decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(20), boxShadow: [BoxShadow(color: Colors.grey.shade200, blurRadius: 15)]),
                child: Form(
                  key: _formKey,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Icon(Icons.event_note_rounded, color: Colors.green.shade700), 
                          const SizedBox(width: 10), 
                          Text("บันทึกงาน: ${DateFormat('dd MMM yyyy').format(_selectedDate)}", style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16, color: Colors.green.shade900))
                        ]
                      ),
                      const Divider(height: 30),
                      
                      SwitchListTile(
                        title: const Text("วันนี้เป็นวันหยุด / ลาพัก", style: TextStyle(fontWeight: FontWeight.bold, color: Colors.orange)),
                        secondary: const Icon(Icons.beach_access, color: Colors.orange),
                        value: _isHoliday,
                        onChanged: (val) => setState(() => _isHoliday = val),
                        activeColor: Colors.orange,
                        contentPadding: EdgeInsets.zero,
                      ),
                      const Divider(),
                      
                      if (!_isHoliday) ...[
                        _buildSectionTitle("รายละเอียดงานที่ทำ *"),
                        TextFormField(controller: _workDoneController, maxLines: 3, decoration: _inputStyle("วันนี้ทำอะไรบ้าง..."), validator: (v) => v!.isEmpty ? 'กรุณากรอกรายละเอียดงาน' : null),
                        const SizedBox(height: 15),
                        _buildSectionTitle("ปัญหา / วิธีแก้ไข"),
                        TextFormField(controller: _problemController, decoration: _inputStyle("ปัญหา...")),
                        const SizedBox(height: 10),
                        TextFormField(controller: _solutionController, decoration: _inputStyle("วิธีแก้...")),
                        const SizedBox(height: 15),
                        _buildSectionTitle("จำนวนชั่วโมงทำงาน *"),
                        TextFormField(controller: _hoursController, keyboardType: const TextInputType.numberWithOptions(decimal: true), decoration: _inputStyle("เช่น 8.0"), validator: (v) => v!.isEmpty ? 'ระบุจำนวนชั่วโมง' : null),
                      ] else 
                        const Padding(padding: EdgeInsets.symmetric(vertical: 20), child: Center(child: Text("บันทึกวันนี้เป็นวันหยุดเพื่อเคลียร์ช่องว่างในปฏิทิน", style: TextStyle(color: Colors.grey)))),

                      const SizedBox(height: 30),
                      
                      SizedBox(
                        width: double.infinity,
                        child: ElevatedButton.icon(
                          onPressed: _isSaving ? null : _saveLog,
                          icon: _isSaving ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2)) : const Icon(Icons.save_rounded, color: Colors.white),
                          label: Text(_isHoliday ? "บันทึกข้อมูลวันหยุด" : "บันทึกข้อมูลรายวัน", style: const TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.bold)),
                          style: ElevatedButton.styleFrom(backgroundColor: _isHoliday ? Colors.orange : Colors.green.shade700, padding: const EdgeInsets.symmetric(vertical: 15), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12))),
                        ),
                      ),

                      if (_endDate != null && isSameDay(_selectedDate, _endDate!)) 
                        _buildSummarySection(),
                    ],
                  ),
                ),
              )
            else
              _buildLockState(),
          ],
        ),
      ),
    );
  }

  Widget _buildSummarySection() {
    List<String> missing = _getMissingDates();
    if (missing.isNotEmpty) {
      return Container(
        width: double.infinity, margin: const EdgeInsets.only(top: 20), padding: const EdgeInsets.all(15),
        decoration: BoxDecoration(color: Colors.red.shade50, borderRadius: BorderRadius.circular(10), border: Border.all(color: Colors.red.shade200)),
        child: Column(
          children: [
            const Icon(Icons.warning_amber_rounded, color: Colors.red, size: 30),
            const SizedBox(height: 5),
            Text("คุณลืมบันทึกงานไป ${missing.length} วัน\nกรุณาบันทึกช่องสีแดงให้ครบก่อนส่งฟอร์มสรุป", textAlign: TextAlign.center, style: const TextStyle(color: Colors.red, fontWeight: FontWeight.bold)),
          ],
        ),
      );
    }
    return Padding(
      padding: const EdgeInsets.only(top: 20),
      child: SizedBox(
        width: double.infinity,
        child: OutlinedButton.icon(
          onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (context) => SummaryFormScreen(internshipId: _internshipId, studentId: widget.studentId))),
          icon: const Icon(Icons.assignment_turned_in, color: Colors.blueAccent),
          label: const Text("กรอกฟอร์มสรุปการฝึกงาน (วันสุดท้าย)", style: TextStyle(color: Colors.blueAccent, fontWeight: FontWeight.bold)),
          style: OutlinedButton.styleFrom(side: const BorderSide(color: Colors.blueAccent, width: 2), padding: const EdgeInsets.symmetric(vertical: 15), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12))),
        ),
      ),
    );
  }

  Widget _buildLockState() => Padding(padding: const EdgeInsets.all(50), child: Column(children: [Icon(Icons.lock_clock_rounded, size: 70, color: Colors.grey.shade300), const SizedBox(height: 20), Text("อยู่นอกช่วงเวลาบันทึกงาน\n(${DateFormat('dd/MM/yy').format(_startDate ?? DateTime.now())} - ${DateFormat('dd/MM/yy').format(_endDate ?? DateTime.now())})", textAlign: TextAlign.center, style: TextStyle(color: Colors.grey.shade500, fontSize: 15))]));
  Widget _buildLegend(Color c, String t) => Row(children: [Container(width: 10, height: 10, decoration: BoxDecoration(color: c, shape: BoxShape.circle)), const SizedBox(width: 4), Text(t, style: const TextStyle(fontSize: 12))]);
  Widget _buildSectionTitle(String t) => Padding(padding: const EdgeInsets.only(bottom: 5), child: Text(t, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: Colors.black87)));
  InputDecoration _inputStyle(String h) => InputDecoration(hintText: h, filled: true, fillColor: Colors.grey.shade50, border: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: BorderSide(color: Colors.grey.shade300)), contentPadding: const EdgeInsets.all(15));
}