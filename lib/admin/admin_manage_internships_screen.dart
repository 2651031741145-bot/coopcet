import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'dart:convert';

class AdminManageInternshipsScreen extends StatefulWidget {
  const AdminManageInternshipsScreen({Key? key}) : super(key: key);

  @override
  State<AdminManageInternshipsScreen> createState() => _AdminManageInternshipsScreenState();
}

class _AdminManageInternshipsScreenState extends State<AdminManageInternshipsScreen> {
  List<dynamic> _internships = [];
  bool _isLoading = true;

  String _searchQuery = "";
  final TextEditingController _searchController = TextEditingController();

  // ตัวแปรสำหรับจัดการตัวกรองปีการศึกษา
  String _selectedYear = "ทั้งหมด";
  List<String> _availableYears = ["ทั้งหมด"];

  @override
  void initState() {
    super.initState();
    _fetchInternships();
    _searchController.addListener(() {
      setState(() {
        _searchQuery = _searchController.text.toLowerCase();
      });
    });
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  Future<void> _fetchInternships() async {
    setState(() => _isLoading = true);
    try {
      final response = await http.get(
        Uri.parse('https://student.cet.rmutr.ac.th/coopcet/internship/app/admin_manage_internships.php'),
      );
      
      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        if (data['success']) {
          setState(() {
            _internships = data['data'];
            
            // ดึงปีการศึกษาที่ไม่ซ้ำกันออกมาทำเป็นตัวเลือกใน Dropdown
            Set<String> yearsSet = {"ทั้งหมด"};
            for (var item in _internships) {
              if (item['academic_year'] != null) {
                yearsSet.add(item['academic_year'].toString());
              }
            }
            _availableYears = yearsSet.toList();
            // เรียงปีการศึกษาจากมากไปน้อย (แต่ให้คำว่า "ทั้งหมด" อยู่บนสุด)
            _availableYears.sort((a, b) => a == "ทั้งหมด" ? -1 : (b == "ทั้งหมด" ? 1 : b.compareTo(a)));
            
            _isLoading = false;
          });
        }
      }
    } catch (e) {
      debugPrint("Fetch Error: $e");
      setState(() => _isLoading = false);
    }
  }

  Future<void> _updateStatus(String internshipId, String newStatus) async {
    try {
      final response = await http.post(
        Uri.parse('https://student.cet.rmutr.ac.th/coopcet/internship/app/admin_manage_internships.php'),
        body: {
          'internship_id': internshipId,
          'status': newStatus,
        },
      );
      
      final data = jsonDecode(response.body);
      if (data['success']) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text(data['message']), backgroundColor: Colors.green)
          );
          _fetchInternships(); 
        }
      } else {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text(data['message']), backgroundColor: Colors.red)
          );
        }
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('เกิดข้อผิดพลาดในการเชื่อมต่อ'), backgroundColor: Colors.red)
        );
      }
    }
  }

  String _getStatusText(String status) {
    switch (status) {
      case 'active': return 'กำลังฝึกงาน';
      case 'relocating': return 'ขอย้ายสถานที่';
      case 'relocated': return 'ย้ายสถานที่แล้ว';
      case 'pending': return 'รออนุมัติจบ';
      case 'finished': return 'จบการฝึกงาน';
      default: return 'ไม่ทราบสถานะ';
    }
  }

  Color _getStatusColor(String status) {
    switch (status) {
      case 'active': return Colors.green.shade600;
      case 'relocating': return Colors.orange.shade600;
      case 'relocated': return Colors.blue.shade600;
      case 'pending': return Colors.amber.shade700;
      case 'finished': return Colors.grey.shade700;
      default: return Colors.black;
    }
  }

  void _showConfirmResetDialog(String internshipId, String studentName) {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(15)),
        title: const Row(
          children: [
            Icon(Icons.warning_amber_rounded, color: Colors.red),
            SizedBox(width: 8),
            Text('ยืนยันการรีเซ็ตข้อมูล', style: TextStyle(color: Colors.red, fontWeight: FontWeight.bold, fontSize: 18)),
          ],
        ),
        content: Text('คุณต้องการลบข้อมูลการยื่นเรื่องของ\n"$studentName"\nใช่หรือไม่?\n\n⚠️ คำเตือน: ระบบจะรีเซ็ตเด็กคนนี้กลับไปเป็นสถานะเหมือนเพิ่งเข้ามาใช้งานครั้งแรก (ประวัติการทำงานในที่ฝึกนี้จะหายไปทั้งหมด)'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('ยกเลิก', style: TextStyle(color: Colors.grey)),
          ),
          ElevatedButton(
            style: ElevatedButton.styleFrom(backgroundColor: Colors.red.shade700),
            onPressed: () {
              Navigator.pop(context);
              _updateStatus(internshipId, 'reset'); 
            },
            child: const Text('ยืนยันรีเซ็ต', style: TextStyle(color: Colors.white)),
          ),
        ],
      ),
    );
  }

  void _showChangeStatusDialog(String internshipId, String currentStatus, String studentName) {
    String selectedStatus = currentStatus;

    showDialog(
      context: context,
      builder: (context) {
        return StatefulBuilder(
          builder: (context, setStateDialog) {
            return AlertDialog(
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(15)),
              title: const Text('ปรับปรุงสถานะการฝึกงาน', style: TextStyle(fontWeight: FontWeight.bold)),
              content: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('นักศึกษา: $studentName', style: const TextStyle(fontWeight: FontWeight.bold)),
                  const SizedBox(height: 15),
                  const Text('เลือกสถานะใหม่:'),
                  const SizedBox(height: 10),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 12),
                    decoration: BoxDecoration(
                      border: Border.all(color: Colors.grey.shade400),
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: DropdownButtonHideUnderline(
                      child: DropdownButton<String>(
                        isExpanded: true,
                        value: selectedStatus,
                        items: const [
                          DropdownMenuItem(value: 'active', child: Text('กำลังฝึกงาน (Active)')),
                          DropdownMenuItem(value: 'relocating', child: Text('ขอย้ายสถานที่ (Relocating)')),
                          DropdownMenuItem(value: 'relocated', child: Text('ย้ายสถานที่แล้ว (Relocated)')),
                          DropdownMenuItem(value: 'pending', child: Text('รออนุมัติจบ (Pending)')),
                          DropdownMenuItem(value: 'finished', child: Text('จบการฝึกงาน (Finished)')),
                          DropdownMenuItem(
                            value: 'reset', 
                            child: Text('ยังไม่ยื่นเรื่อง (รีเซ็ตข้อมูลใหม่)', style: TextStyle(color: Colors.red, fontWeight: FontWeight.bold))
                          ),
                        ],
                        onChanged: (value) {
                          if (value != null) {
                            setStateDialog(() => selectedStatus = value);
                          }
                        },
                      ),
                    ),
                  ),
                ],
              ),
              actions: [
                TextButton(
                  onPressed: () => Navigator.pop(context),
                  child: const Text('ยกเลิก', style: TextStyle(color: Colors.grey)),
                ),
                ElevatedButton(
                  style: ElevatedButton.styleFrom(backgroundColor: Colors.teal.shade700),
                  onPressed: () {
                    Navigator.pop(context); 
                    if (selectedStatus != currentStatus) {
                      if (selectedStatus == 'reset') {
                        _showConfirmResetDialog(internshipId, studentName);
                      } else {
                        _updateStatus(internshipId, selectedStatus);
                      }
                    }
                  },
                  child: const Text('บันทึก', style: TextStyle(color: Colors.white)),
                ),
              ],
            );
          }
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    final primaryColor = const Color.fromARGB(255, 26, 135, 1);

    // กรองข้อมูลตาม "การค้นหา" และ "ปีการศึกษา"
    final filteredInternships = _internships.where((item) {
      final name = item['full_name'].toString().toLowerCase();
      final studentId = item['student_id'].toString().toLowerCase();
      final itemYear = item['academic_year'].toString();

      final matchSearch = name.contains(_searchQuery) || studentId.contains(_searchQuery);
      final matchYear = _selectedYear == "ทั้งหมด" || itemYear == _selectedYear;

      return matchSearch && matchYear;
    }).toList();

    return Scaffold(
      backgroundColor: Colors.grey[100],
      appBar: AppBar(
        title: const Text("จัดการสถานะการฝึกงาน", style: TextStyle(fontWeight: FontWeight.bold)),
        backgroundColor: primaryColor,
        foregroundColor: Colors.white,
      ),
      body: Column(
        children: [
          Container(
            padding: const EdgeInsets.only(top: 16, left: 16, right: 16, bottom: 12),
            color: Colors.white,
            child: Column(
              children: [
                // ช่องค้นหา
                TextField(
                  controller: _searchController,
                  decoration: InputDecoration(
                    hintText: 'ค้นหาชื่อ หรือ รหัสนักศึกษา...',
                    prefixIcon: const Icon(Icons.search),
                    filled: true,
                    fillColor: Colors.grey.shade100,
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(30),
                      borderSide: BorderSide.none,
                    ),
                    contentPadding: const EdgeInsets.symmetric(vertical: 0),
                  ),
                ),
                const SizedBox(height: 12),
                
                // 🚨 เปลี่ยนจากแนวนอนเป็น Dropdown เรียบหรู
                Row(
                  children: [
                    const Icon(Icons.filter_list_rounded, color: Colors.grey),
                    const SizedBox(width: 8),
                    const Text("กรองปีการศึกษา(ตามรหัส):", style: TextStyle(fontWeight: FontWeight.bold, color: Colors.grey)),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Container(
                        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 2),
                        decoration: BoxDecoration(
                          color: Colors.grey.shade100,
                          borderRadius: BorderRadius.circular(20),
                          border: Border.all(color: Colors.grey.shade300),
                        ),
                        child: DropdownButtonHideUnderline(
                          child: DropdownButton<String>(
                            isExpanded: true,
                            value: _selectedYear,
                            icon: Icon(Icons.arrow_drop_down, color: primaryColor),
                            dropdownColor: Colors.white,
                            style: TextStyle(color: primaryColor, fontWeight: FontWeight.bold, fontSize: 14),
                            items: _availableYears.map((String year) {
                              return DropdownMenuItem<String>(
                                value: year,
                                child: Text(year == "ทั้งหมด" ? "แสดงทุกปีการศึกษา" : "นักศึกษารหัสปี $year"),
                              );
                            }).toList(),
                            onChanged: (String? newValue) {
                              if (newValue != null) {
                                setState(() {
                                  _selectedYear = newValue;
                                });
                              }
                            },
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
          
          Expanded(
            child: _isLoading
              ? const Center(child: CircularProgressIndicator())
              : filteredInternships.isEmpty
                ? Center(
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Icon(Icons.inbox_rounded, size: 60, color: Colors.grey.shade400),
                        const SizedBox(height: 10),
                        Text('ไม่พบข้อมูลการฝึกงาน', style: TextStyle(color: Colors.grey.shade600, fontSize: 16)),
                      ],
                    ),
                  )
                : ListView.builder(
                    padding: const EdgeInsets.all(12),
                    itemCount: filteredInternships.length,
                    itemBuilder: (context, index) {
                      final item = filteredInternships[index];
                      final status = item['status'].toString();
                      final internshipId = item['internship_id'].toString();
                      
                      return Card(
                        elevation: 2,
                        margin: const EdgeInsets.only(bottom: 12),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(15)),
                        child: Padding(
                          padding: const EdgeInsets.all(16.0),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Row(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  CircleAvatar(
                                    backgroundColor: primaryColor.withOpacity(0.1),
                                    child: const Icon(Icons.person, color: Color.fromARGB(255, 26, 135, 1)),
                                  ),
                                  const SizedBox(width: 12),
                                  Expanded(
                                    child: Column(
                                      crossAxisAlignment: CrossAxisAlignment.start,
                                      children: [
                                        Text(
                                          item['full_name'] ?? 'ไม่ระบุชื่อ',
                                          style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
                                        ),
                                        Text(
                                          "รหัส: ${item['student_id']}",
                                          style: TextStyle(color: Colors.grey.shade600, fontSize: 13),
                                        ),
                                      ],
                                    ),
                                  ),
                                  InkWell(
                                    borderRadius: BorderRadius.circular(20),
                                    onTap: () => _showChangeStatusDialog(internshipId, status, item['full_name']),
                                    child: Container(
                                      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                                      decoration: BoxDecoration(
                                        color: _getStatusColor(status).withOpacity(0.1),
                                        borderRadius: BorderRadius.circular(20),
                                        border: Border.all(color: _getStatusColor(status).withOpacity(0.5)),
                                      ),
                                      child: Row(
                                        mainAxisSize: MainAxisSize.min,
                                        children: [
                                          Container(
                                            width: 8, height: 8,
                                            decoration: BoxDecoration(shape: BoxShape.circle, color: _getStatusColor(status)),
                                          ),
                                          const SizedBox(width: 6),
                                          Text(
                                            _getStatusText(status),
                                            style: TextStyle(color: _getStatusColor(status), fontWeight: FontWeight.bold, fontSize: 12),
                                          ),
                                          const SizedBox(width: 4),
                                          Icon(Icons.edit, size: 12, color: _getStatusColor(status)),
                                        ],
                                      ),
                                    ),
                                  ),
                                ],
                              ),
                              const Divider(height: 24),
                              Row(
                                children: [
                                  const Icon(Icons.business, size: 16, color: Colors.grey),
                                  const SizedBox(width: 8),
                                  Expanded(
                                    child: Text(
                                      item['company_name'] ?? 'ไม่ระบุสถานที่',
                                      style: TextStyle(color: Colors.grey.shade800),
                                      maxLines: 1,
                                      overflow: TextOverflow.ellipsis,
                                    ),
                                  ),
                                  Container(
                                    padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                                    decoration: BoxDecoration(
                                      color: Colors.grey.shade200,
                                      borderRadius: BorderRadius.circular(4),
                                    ),
                                    child: Text(
                                      "ปี ${item['academic_year']}",
                                      style: TextStyle(fontSize: 10, color: Colors.grey.shade700, fontWeight: FontWeight.bold),
                                    ),
                                  ),
                                ],
                              ),
                            ],
                          ),
                        ),
                      );
                    },
                  ),
          ),
        ],
      ),
    );
  }
}