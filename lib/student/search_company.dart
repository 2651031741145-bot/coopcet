import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'dart:convert';
import 'package:url_launcher/url_launcher.dart'; // อย่าลืม import package นี้นะครับ
import 'add_company_map.dart';

class SearchCompanyScreen extends StatefulWidget {
  const SearchCompanyScreen({Key? key}) : super(key: key);

  @override
  State<SearchCompanyScreen> createState() => _SearchCompanyScreenState();
}

class _SearchCompanyScreenState extends State<SearchCompanyScreen> {
  List<dynamic> _companies = [];
  bool _isLoading = true;
  final TextEditingController _searchController = TextEditingController();

  @override
  void initState() {
    super.initState();
    _fetchCompanies(""); // โหลดทั้งหมดตอนเริ่ม
  }

  Future<void> _fetchCompanies(String query) async {
    setState(() => _isLoading = true);
    try {
      final response = await http.get(
        Uri.parse('https://student.cet.rmutr.ac.th/coopcet/internship/app/search_companies.php?q=$query'),
      );
      if (response.statusCode == 200) {
        setState(() {
          _companies = jsonDecode(response.body);
          _isLoading = false;
        });
      }
    } catch (e) {
      debugPrint("Search Error: $e");
      setState(() => _isLoading = false);
    }
  }

  // เมื่อนักศึกษาเลือกบริษัท
  void _selectCompany(int companyId, String companyName) {
    Navigator.pop(context, {'id': companyId, 'name': companyName});
  }

  // --- ฟังก์ชันใหม่: เปิดดูพิกัดบน Google Maps ---
  Future<void> _openMap(String? lat, String? lng) async {
    if (lat == null || lng == null || lat.isEmpty || lng.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('ไม่มีข้อมูลพิกัดสำหรับสถานที่นี้'), backgroundColor: Colors.orange)
      );
      return;
    }
    
    // สร้าง URL ค้นหาพิกัดใน Google Maps
    final Uri uri = Uri.parse("https://www.google.com/maps/search/?api=1&query=$lat,$lng");

    if (await canLaunchUrl(uri)) {
      await launchUrl(uri, mode: LaunchMode.externalApplication);
    } else {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('ไม่สามารถเปิด Google Maps ได้'), backgroundColor: Colors.red)
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('เลือกสถานที่ฝึกงาน', style: TextStyle(fontWeight: FontWeight.bold)),
        backgroundColor: Colors.indigo,
        foregroundColor: Colors.white,
        elevation: 0,
      ),
      backgroundColor: Colors.grey[50],
      body: Column(
        children: [
          // ช่องค้นหา
          Container(
            color: Colors.white,
            padding: const EdgeInsets.all(16.0),
            child: TextField(
              controller: _searchController,
              decoration: InputDecoration(
                hintText: 'ค้นหาชื่อบริษัท หรือ ที่อยู่...',
                prefixIcon: const Icon(Icons.search, color: Colors.indigo),
                suffixIcon: IconButton(
                  icon: const Icon(Icons.clear),
                  onPressed: () {
                    _searchController.clear();
                    _fetchCompanies("");
                  },
                ),
                border: OutlineInputBorder(borderRadius: BorderRadius.circular(15), borderSide: BorderSide.none),
                filled: true,
                fillColor: Colors.grey.shade100,
                contentPadding: const EdgeInsets.symmetric(vertical: 15)
              ),
              onChanged: (value) => _fetchCompanies(value),
            ),
          ),
          
          // รายการบริษัท
          Expanded(
            child: _isLoading
                ? const Center(child: CircularProgressIndicator(color: Colors.indigo))
                : _companies.isEmpty
                    ? _buildEmptyState()
                    : ListView.builder(
                        itemCount: _companies.length,
                        padding: const EdgeInsets.only(top: 10, bottom: 20),
                        itemBuilder: (context, index) {
                          final comp = _companies[index];
                          return Card(
                            margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                            elevation: 0,
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(15),
                              side: BorderSide(color: Colors.grey.shade200)
                            ),
                            child: Padding(
                              padding: const EdgeInsets.all(16.0),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Row(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      CircleAvatar(
                                        backgroundColor: Colors.indigo.shade50,
                                        child: const Icon(Icons.business, color: Colors.indigo),
                                      ),
                                      const SizedBox(width: 15),
                                      Expanded(
                                        child: Column(
                                          crossAxisAlignment: CrossAxisAlignment.start,
                                          children: [
                                            Text(comp['company_name'], style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
                                            const SizedBox(height: 5),
                                            Text(comp['address'], maxLines: 2, overflow: TextOverflow.ellipsis, style: TextStyle(color: Colors.grey.shade600, fontSize: 13)),
                                          ],
                                        ),
                                      ),
                                    ],
                                  ),
                                  const Divider(height: 30),
                                  
                                  // --- ปรับปรุงปุ่มกดใหม่ แยกส่วนแผนที่และการเลือก ---
                                  Row(
                                    children: [
                                      Expanded(
                                        child: OutlinedButton.icon(
                                          onPressed: () => _openMap(comp['latitude']?.toString(), comp['longitude']?.toString()),
                                          icon: const Icon(Icons.map_outlined, size: 18, color: Colors.blue),
                                          label: const Text("ดูแผนที่", style: TextStyle(color: Colors.blue)),
                                          style: OutlinedButton.styleFrom(
                                            side: BorderSide(color: Colors.blue.shade200),
                                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10))
                                          ),
                                        ),
                                      ),
                                      const SizedBox(width: 10),
                                      Expanded(
                                        child: ElevatedButton.icon(
                                          onPressed: () => _selectCompany(int.parse(comp['company_id'].toString()), comp['company_name']),
                                          icon: const Icon(Icons.check_circle_outline, size: 18, color: Colors.white),
                                          label: const Text("เลือกที่นี่", style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
                                          style: ElevatedButton.styleFrom(
                                            backgroundColor: Colors.green.shade600,
                                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10))
                                          ),
                                        ),
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
          
          // ปุ่มเพิ่มสถานที่ใหม่ กรณีหาไม่เจอ
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: Colors.white,
              boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.05), blurRadius: 10, offset: const Offset(0, -5))]
            ),
            width: double.infinity,
            child: SafeArea(
              child: OutlinedButton.icon(
                onPressed: () async {
                  final newCompanyId = await Navigator.push(
                    context,
                    MaterialPageRoute(builder: (context) => const AddCompanyMapScreen()),
                  );
                  if (newCompanyId != null) {
                    if(mounted) {
                      Navigator.pop(context, {'id': newCompanyId, 'name': 'บริษัทใหม่ (ID: $newCompanyId)'});
                    }
                  }
                },
                icon: const Icon(Icons.add_location_alt),
                label: const Text("ไม่พบชื่อบริษัท? เพิ่มสถานที่ใหม่"),
                style: OutlinedButton.styleFrom(
                  foregroundColor: Colors.indigo,
                  side: const BorderSide(color: Colors.indigo, width: 1.5),
                  padding: const EdgeInsets.symmetric(vertical: 15),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12))
                ),
              ),
            ),
          )
        ],
      ),
    );
  }

  Widget _buildEmptyState() {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(Icons.business_center_outlined, size: 80, color: Colors.grey.shade300),
          const SizedBox(height: 16),
          const Text("ไม่พบสถานประกอบการที่ค้นหา", style: TextStyle(color: Colors.grey, fontSize: 16, fontWeight: FontWeight.bold)),
          const SizedBox(height: 5),
          const Text("กรุณากดปุ่มเพิ่มสถานที่ใหม่ด้านล่าง", style: TextStyle(color: Colors.grey, fontSize: 14)),
        ],
      ),
    );
  }
}