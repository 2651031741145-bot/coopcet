import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'dart:convert';
// 🚨 1. นำเข้าไฟล์หน้าจอใหม่
import 'admin_company_form_screen.dart'; 

class AdminManageCompaniesScreen extends StatefulWidget {
  const AdminManageCompaniesScreen({Key? key}) : super(key: key);

  @override
  State<AdminManageCompaniesScreen> createState() => _AdminManageCompaniesScreenState();
}

class _AdminManageCompaniesScreenState extends State<AdminManageCompaniesScreen> {
  List<dynamic> _companies = [];
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _fetchCompanies();
  }

  Future<void> _fetchCompanies() async {
    setState(() => _isLoading = true);
    try {
      final response = await http.get(
        Uri.parse('https://student.cet.rmutr.ac.th/coopcet/internship/app/admin_manage_companies.php?action=read'),
      );
      final data = jsonDecode(response.body);
      if (data['success']) {
        setState(() {
          _companies = data['data'];
          _isLoading = false;
        });
      }
    } catch (e) {
      setState(() => _isLoading = false);
      _showSnackBar("เกิดข้อผิดพลาดในการโหลดข้อมูล", Colors.red);
    }
  }

  Future<void> _deleteCompany(String id, String name) async {
    // 🚨 ตัดคำว่า (ID: ตัวเลข) ออกจากชื่อบริษัทเวลาแสดงใน Popup ลบ
    String cleanCompanyName = name.replaceAll(RegExp(r'\s*\(ID:\s*\d+\)'), '');

    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text("ยืนยันการลบ", style: TextStyle(color: Colors.red, fontWeight: FontWeight.bold)),
        content: Text("คุณแน่ใจหรือไม่ว่าต้องการลบ\n\n'$cleanCompanyName'\n\nใช่หรือไม่?"),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context), child: const Text("ยกเลิก", style: TextStyle(color: Colors.grey))),
          ElevatedButton(
            style: ElevatedButton.styleFrom(backgroundColor: Colors.red),
            onPressed: () async {
              Navigator.pop(context);
              try {
                final response = await http.post(
                  Uri.parse('https://student.cet.rmutr.ac.th/coopcet/internship/app/admin_manage_companies.php'),
                  body: {'action': 'delete', 'company_id': id},
                );
                final data = jsonDecode(response.body);
                if (data['success']) {
                  _showSnackBar(data['message'], Colors.green);
                  _fetchCompanies(); // โหลดข้อมูลใหม่
                } else {
                  _showSnackBar(data['message'], Colors.red);
                }
              } catch (e) {
                _showSnackBar("เกิดข้อผิดพลาดในการเชื่อมต่อ", Colors.red);
              }
            },
            child: const Text("ลบทิ้ง", style: TextStyle(color: Colors.white)),
          ),
        ],
      ),
    );
  }

  // 🚨 2. ลบฟังก์ชัน _showFormDialog เดิมทิ้ง แล้วสร้างฟังก์ชันนี้ขึ้นมาแทน
  Future<void> _openFormScreen({Map<String, dynamic>? companyData}) async {
    // Navigator.push เพื่อเปิดหน้าจอใหม่
    final result = await Navigator.push(
      context,
      MaterialPageRoute(
        builder: (context) => AdminCompanyFormScreen(companyData: companyData),
      ),
    );

    // 🚨 เช็คถ้าหน้าที่เปิดไปส่งค่า true กลับมา แสดงว่าบันทึกสำเร็จ ให้เราโหลดข้อมูลใหม่
    if (result == true && mounted) {
      _fetchCompanies();
    }
  }

  void _showSnackBar(String msg, Color color) {
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(msg), backgroundColor: color));
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text("จัดการสถานประกอบการ", style: TextStyle(fontWeight: FontWeight.bold)),
        backgroundColor: Colors.teal.shade800,
        foregroundColor: Colors.white,
      ),
      body: _isLoading 
        ? const Center(child: CircularProgressIndicator(color: Colors.teal))
        : _companies.isEmpty 
          ? const Center(child: Text("ยังไม่มีข้อมูลสถานประกอบการ", style: TextStyle(color: Colors.grey)))
          : ListView.builder(
              padding: const EdgeInsets.all(10),
              itemCount: _companies.length,
              itemBuilder: (context, index) {
                final company = _companies[index];
                String rawName = company['company_name'] ?? '-';
                
                // 🚨 ตัดคำว่า (ID: ตัวเลข) ออกจากชื่อบริษัทเวลาแสดงผลในหน้านี้
                String cleanCompanyName = rawName.replaceAll(RegExp(r'\s*\(ID:\s*\d+\)'), '');

                return Card(
                  elevation: 2,
                  margin: const EdgeInsets.symmetric(vertical: 8),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  child: ListTile(
                    contentPadding: const EdgeInsets.all(15),
                    leading: CircleAvatar(backgroundColor: Colors.teal.shade100, child: const Icon(Icons.business, color: Colors.teal)),
                    title: Text(cleanCompanyName, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
                    subtitle: Padding(
                      padding: const EdgeInsets.only(top: 8.0),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(company['address'] ?? '-', maxLines: 2, overflow: TextOverflow.ellipsis),
                          const SizedBox(height: 5),
                          if (company['latitude'] != null && company['latitude'].toString().isNotEmpty)
                            Text("พิกัด: ${company['latitude']}, ${company['longitude']}", style: TextStyle(color: Colors.blue.shade700, fontSize: 12)),
                        ],
                      ),
                    ),
                    trailing: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        IconButton(
                          icon: const Icon(Icons.edit, color: Colors.orange),
                          // 🚨 3. แก้ให้เรียกใช้ฟังก์ชันใหม่
                          onPressed: () => _openFormScreen(companyData: company),
                        ),
                        IconButton(
                          icon: const Icon(Icons.delete, color: Colors.red),
                          // 🚨 4. แก้ให้ส่งชื่อบริษัทไปด้วยเพื่อตัดคำ
                          onPressed: () => _deleteCompany(company['company_id'].toString(), rawName),
                        ),
                      ],
                    ),
                  ),
                );
              },
            ),
      floatingActionButton: FloatingActionButton.extended(
        // 🚨 5. แก้ให้เรียกใช้ฟังก์ชันใหม่แบบไม่ส่งข้อมูลสถานที่ไป
        onPressed: () => _openFormScreen(),
        backgroundColor: Colors.teal.shade700,
        foregroundColor: Colors.white,
        icon: const Icon(Icons.add_business),
        label: const Text("เพิ่มสถานที่"),
      ),
    );
  }
}