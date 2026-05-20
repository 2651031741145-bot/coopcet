import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'dart:convert';
import 'archive_detail_screen.dart'; // อย่าลืมสร้างไฟล์นี้ในส่วนถัดไปนะครับ

class ArchiveScreen extends StatefulWidget {
  const ArchiveScreen({Key? key}) : super(key: key);

  @override
  State<ArchiveScreen> createState() => _ArchiveScreenState();
}

class _ArchiveScreenState extends State<ArchiveScreen> {
  List<dynamic> _students = [];
  List<String> _availableYears = [];
  String _selectedYear = 'all';
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _fetchData();
  }

  Future<void> _fetchData() async {
    setState(() => _isLoading = true);
    try {
      final response = await http.get(Uri.parse(
          'https://student.cet.rmutr.ac.th/coopcet/internship/app/get_finished_students.php?year=$_selectedYear'));
      final data = jsonDecode(response.body);
      if (data['success']) {
        setState(() {
          _students = data['data'];
          if (_availableYears.isEmpty && data['years'] != null) {
            _availableYears = List<String>.from(data['years']);
          }
          _isLoading = false;
        });
      }
    } catch (e) {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text("คลังข้อมูลผู้ผ่านการฝึกงาน"),
        backgroundColor: Colors.indigo.shade800,
        foregroundColor: Colors.white,
      ),
      body: Column(
        children: [
          // ตัวกรองปี พ.ศ.
          Padding(
            padding: const EdgeInsets.all(15),
            child: Row(
              children: [
                const Icon(Icons.filter_alt_outlined, color: Colors.indigo),
                const SizedBox(width: 10),
                const Text("ปีที่ฝึก:",
                    style: TextStyle(fontWeight: FontWeight.bold)),
                const SizedBox(width: 10),
                Expanded(
                  child: DropdownButtonFormField<String>(
                    value: _selectedYear,
                    decoration: const InputDecoration(
                        contentPadding: EdgeInsets.symmetric(horizontal: 10),
                        border: OutlineInputBorder()),
                    items: [
                      const DropdownMenuItem(
                          value: 'all', child: Text('ทั้งหมด')),
                      ..._availableYears
                          .map(
                              (y) => DropdownMenuItem(value: y, child: Text(y)))
                          .toList(),
                    ],
                    onChanged: (val) {
                      if (val != null) {
                        setState(() => _selectedYear = val);
                        _fetchData();
                      }
                    },
                  ),
                ),
              ],
            ),
          ),
          // ตารางข้อมูล 7 หัวข้อ
          Expanded(
            child: _isLoading
                ? const Center(child: CircularProgressIndicator())
                : SingleChildScrollView(
                    scrollDirection: Axis.horizontal,
                    child: SingleChildScrollView(
                      child: DataTable(
                        showCheckboxColumn: false,
                        headingRowColor:
                            MaterialStateProperty.all(Colors.indigo.shade50),
                        columns: const [
                          DataColumn(label: Text('ปีที่ฝึก')),
                          DataColumn(label: Text('รหัส')),
                          DataColumn(label: Text('ชื่อ-นามสกุล')),
                          DataColumn(label: Text('สถานที่')),
                          DataColumn(label: Text('ตำแหน่ง')),
                          DataColumn(label: Text('สวัสดิการ')),
                          DataColumn(label: Text('อาจารย์นิเทศ')),
                        ],
                        rows: _students.map((s) {
                          return DataRow(
                            onSelectChanged: (selected) {
                              if (selected != null) {
                                // เมื่อกดแถวนั้น ให้ไปหน้าแสดงรายละเอียด
                                Navigator.push(
                                    context,
                                    MaterialPageRoute(
                                        builder: (context) =>
                                            ArchiveDetailScreen(
                                                studentData: s)));
                              }
                            },
                            cells: [
                              DataCell(Text(s['internship_year'] ?? '-')),
                              DataCell(Text(s['student_id'] ?? '-')),
                              DataCell(Text(s['full_name'] ?? '-')),
                              DataCell(Text(s['company_name'] ?? '-')),
                              DataCell(Text(s['position'] ?? '-')),
                              DataCell(
                                SizedBox(
                                  width:
                                      150, // จำกัดความกว้างเพื่อไม่ให้ตารางเบี้ยว
                                  child: Text(
                                    s['has_benefits'] == 'yes'
                                        ? "${s['benefit_details']}" // แสดงรายละเอียดต่อท้าย
                                        : 'ไม่มี',
                                    overflow: TextOverflow
                                        .ellipsis, // ถ้าข้อความยาวเกินให้ใส่ ...
                                  ),
                                ),
                              ),
                              DataCell(Text(s['supervisor_name'] ?? '-')),
                            ],
                          );
                        }).toList(),
                      ),
                    ),
                  ),
          ),
        ],
      ),
    );
  }
}
