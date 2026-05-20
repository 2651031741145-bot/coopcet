import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';
import 'package:http/http.dart' as http;
import 'dart:convert';
import 'package:url_launcher/url_launcher.dart';

class AdminCompanyFormScreen extends StatefulWidget {
  // รับข้อมูลสถานที่ (ถ้ามี) สำหรับกรณีแก้ไข
  final Map<String, dynamic>? companyData;

  const AdminCompanyFormScreen({Key? key, this.companyData}) : super(key: key);

  @override
  State<AdminCompanyFormScreen> createState() => _AdminCompanyFormScreenState();
}

class _AdminCompanyFormScreenState extends State<AdminCompanyFormScreen> {
  final _formKey = GlobalKey<FormState>();
  final _nameController = TextEditingController();
  final _addressController = TextEditingController();
  final MapController _mapController = MapController();

  // พิกัดเริ่มต้น (กรุงเทพมหานคร ประเทศไทย) หรือพิกัดเดิมถ้าแก้ไข
  LatLng _selectedLocation = const LatLng(13.7563, 100.5018);
  bool _isSaving = false;

  bool get _isEdit => widget.companyData != null;

  @override
  void initState() {
    super.initState();
    // ถ้าเป็นการแก้ไข ให้ดึงข้อมูลเดิมมาใส่ในฟอร์มและปักหมุดแผนที่
    if (_isEdit) {
      final c = widget.companyData!;
      _nameController.text = c['company_name'] ?? '';
      _addressController.text = c['address'] ?? '';
      
      final double? lat = double.tryParse(c['latitude']?.toString() ?? '');
      final double? lon = double.tryParse(c['longitude']?.toString() ?? '');
      
      if (lat != null && lon != null) {
        _selectedLocation = LatLng(lat, lon);
      }
    }
  }

  // ฟังก์ชันส่งพิกัดไปเปิดใน Google Maps เพื่อตรวจสอบ
  Future<void> _checkOnGoogleMaps() async {
    final double lat = _selectedLocation.latitude;
    final double lng = _selectedLocation.longitude;
    // สร้าง URL ของ Google Maps โดยใส่พิกัดปัจจุบัน
    final String googleMapsUrl = "https://www.google.com/maps/search/?api=1&query=$lat,$lng";
    final Uri uri = Uri.parse(googleMapsUrl);

    if (await canLaunchUrl(uri)) {
      await launchUrl(uri, mode: LaunchMode.externalApplication); // บังคับเปิดแอป Google Maps
    } else {
      _showSnackBar('ไม่สามารถเปิด Google Maps ได้', Colors.orange);
    }
  }

  // ฟังก์ชันค้นหาสถานที่จาก API (จำกัดแค่ในไทย) หรือ ดึงพิกัดที่วาง
  Future<Iterable<Map<String, dynamic>>> _searchPlaces(String query) async {
    if (query.isEmpty) return const Iterable<Map<String, dynamic>>.empty();

    // 1. เช็คว่าผู้ใช้พิมพ์/วาง "พิกัด" หรือไม่ (เช่น 13.123, 100.123)
    final coordRegExp = RegExp(r'^(-?\d+(\.\d+)?)\s*,\s*(-?\d+(\.\d+)?)$');
    final match = coordRegExp.firstMatch(query.trim());
    
    if (match != null) {
      double lat = double.parse(match.group(1)!);
      double lng = double.parse(match.group(3)!);
      return [
        {
          'display_name': '📌 ไปที่พิกัด: $lat, $lng',
          'lat': lat.toString(),
          'lon': lng.toString()
        }
      ];
    }

    // 2. ถ้าไม่ใช่พิกัด ให้ค้นหาจากชื่อสถานที่ผ่าน OpenStreetMap (จำกัดในไทย)
    if (query.length < 3) return const Iterable<Map<String, dynamic>>.empty(); // พิมพ์เกิน 3 ตัวอักษรค่อยค้นหา เพื่อลดโหลด

    final url = Uri.parse(
        'https://nominatim.openstreetmap.org/search?q=$query&format=json&countrycodes=th&addressdetails=1&limit=5');
    
    try {
      final response = await http.get(url, headers: {
        'User-Agent': 'com.cet.internship' // จำเป็นต้องใส่ User-Agent สำหรับ Nominatim API
      });
      
      if (response.statusCode == 200) {
        final List data = jsonDecode(response.body);
        return data.cast<Map<String, dynamic>>();
      }
    } catch (e) {
      debugPrint('Search error: $e');
    }
    return const Iterable<Map<String, dynamic>>.empty();
  }

  Future<void> _saveData() async {
    if (!_formKey.currentState!.validate()) return;
    
    setState(() => _isSaving = true);
    try {
      final Map<String, String> body = {
        'action': _isEdit ? 'update' : 'add',
        'company_name': _nameController.text.trim(),
        'address': _addressController.text.trim(),
        'latitude': _selectedLocation.latitude.toString(),
        'longitude': _selectedLocation.longitude.toString(),
      };

      if (_isEdit) {
        body['company_id'] = widget.companyData!['company_id'].toString();
      }

      final response = await http.post(
        Uri.parse('https://student.cet.rmutr.ac.th/coopcet/internship/app/admin_manage_companies.php'),
        body: body,
      );
      
      final data = jsonDecode(response.body);
      if (data['success']) {
        if (mounted) {
          _showSnackBar(data['message'], Colors.green);
          Navigator.pop(context, true); // ส่งค่า true กลับไปบอกหน้าหลักว่าบันทึกสำเร็จ
        }
      } else {
        _showSnackBar(data['message'], Colors.red);
      }
    } catch (e) {
      _showSnackBar('เกิดข้อผิดพลาด: $e', Colors.red);
    } finally {
      if (mounted) setState(() => _isSaving = false);
    }
  }

  void _showSnackBar(String msg, Color color) {
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(msg), backgroundColor: color));
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.grey[50],
      appBar: AppBar(
        title: Text(_isEdit ? 'แก้ไขสถานประกอบการ' : 'เพิ่มสถานประกอบการใหม่', style: const TextStyle(fontWeight: FontWeight.bold)),
        backgroundColor: Colors.teal.shade800,
        foregroundColor: Colors.white,
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.all(16.0),
            child: Form(
              key: _formKey,
              child: Column(
                children: [
                  TextFormField(
                    controller: _nameController,
                    decoration: const InputDecoration(labelText: 'ชื่อสถานประกอบการ *', border: OutlineInputBorder(), prefixIcon: Icon(Icons.business)),
                    validator: (val) => val!.isEmpty ? 'กรุณากรอกชื่อบริษัท' : null,
                  ),
                  const SizedBox(height: 12),
                  TextFormField(
                    controller: _addressController,
                    maxLines: 2,
                    decoration: const InputDecoration(labelText: 'ที่อยู่แบบละเอียด *', border: OutlineInputBorder(), prefixIcon: Icon(Icons.location_city)),
                    validator: (val) => val!.isEmpty ? 'กรุณากรอกที่อยู่' : null,
                  ),
                  const SizedBox(height: 12),
                  
                  // ช่องค้นหาสถานที่/วางพิกัด แบบ Auto-complete
                  Autocomplete<Map<String, dynamic>>(
                    optionsBuilder: (TextEditingValue textEditingValue) {
                      return _searchPlaces(textEditingValue.text);
                    },
                    displayStringForOption: (option) => option['display_name'] ?? '',
                    onSelected: (option) {
                      double lat = double.parse(option['lat'].toString());
                      double lon = double.parse(option['lon'].toString());
                      
                      setState(() {
                        _selectedLocation = LatLng(lat, lon);
                      });
                      
                      // เลื่อนแผนที่ไปที่ตำแหน่งที่เลือก และซูมเข้าไปใกล้ๆ
                      _mapController.move(_selectedLocation, 17.0);
                      
                      // ซ่อนคีย์บอร์ด
                      FocusScope.of(context).unfocus();
                    },
                    fieldViewBuilder: (context, textEditingController, focusNode, onFieldSubmitted) {
                      return TextFormField(
                        controller: textEditingController,
                        focusNode: focusNode,
                        decoration: InputDecoration(
                          labelText: 'ค้นหาสถานที่ หรือ วางพิกัด (เช่น 13.75, 100.50)',
                          hintText: 'พิมพ์ชื่อบริษัท หรือตำบล/อำเภอ',
                          border: const OutlineInputBorder(),
                          prefixIcon: const Icon(Icons.search, color: Colors.teal),
                          suffixIcon: IconButton(
                            icon: const Icon(Icons.clear),
                            onPressed: () {
                              textEditingController.clear();
                            },
                          ),
                        ),
                      );
                    },
                    optionsViewBuilder: (context, onSelected, options) {
                      return Align(
                        alignment: Alignment.topLeft,
                        child: Material(
                          elevation: 4.0,
                          child: ConstrainedBox(
                            constraints: BoxConstraints(
                              maxHeight: 200,
                              maxWidth: MediaQuery.of(context).size.width - 32,
                            ),
                            child: ListView.builder(
                              padding: EdgeInsets.zero,
                              shrinkWrap: true,
                              itemCount: options.length,
                              itemBuilder: (BuildContext context, int index) {
                                final option = options.elementAt(index);
                                return ListTile(
                                  leading: const Icon(Icons.location_on, color: Colors.redAccent),
                                  title: Text(
                                    option['display_name'] ?? '',
                                    maxLines: 2,
                                    overflow: TextOverflow.ellipsis,
                                    style: const TextStyle(fontSize: 14),
                                  ),
                                  onTap: () => onSelected(option),
                                );
                              },
                            ),
                          ),
                        ),
                      );
                    },
                  ),
                ],
              ),
            ),
          ),
          
          // แถบคำแนะนำและปุ่มเช็คใน Google Maps
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16.0, vertical: 8.0),
            child: Row(
              children: [
                const Icon(Icons.touch_app, color: Colors.teal),
                const SizedBox(width: 8),
                const Expanded(child: Text('แตะแผนที่เพื่อปักหมุด', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13))),
                OutlinedButton.icon(
                  onPressed: _checkOnGoogleMaps,
                  icon: const Icon(Icons.map, size: 16, color: Colors.blue),
                  label: const Text('เช็คพิกัดบน Map', style: TextStyle(fontSize: 12, color: Colors.blue, fontWeight: FontWeight.bold)),
                  style: OutlinedButton.styleFrom(
                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                    minimumSize: Size.zero,
                    side: BorderSide(color: Colors.blue.shade300),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8))
                  ),
                )
              ],
            ),
          ),

          // แผนที่
          Expanded(
            child: FlutterMap(
              mapController: _mapController, 
              options: MapOptions(
                initialCenter: _selectedLocation,
                initialZoom: _isEdit ? 16.0 : 13.0,
                onTap: (tapPosition, point) {
                  setState(() {
                    _selectedLocation = point;
                  });
                },
              ),
              children: [
                TileLayer(
                  urlTemplate: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
                  userAgentPackageName: 'com.cet.internship',
                ),
                MarkerLayer(
                  markers: [
                    Marker(
                      point: _selectedLocation,
                      width: 50,
                      height: 50,
                      child: const Icon(Icons.location_on, color: Colors.red, size: 45),
                    ),
                  ],
                ),
              ],
            ),
          ),

          // ปุ่มบันทึกด้านล่าง
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(16),
            color: Colors.white,
            child: ElevatedButton(
              onPressed: _isSaving ? null : _saveData,
              style: ElevatedButton.styleFrom(
                backgroundColor: Colors.teal.shade700,
                padding: const EdgeInsets.symmetric(vertical: 15),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10))
              ),
              child: _isSaving 
                ? const CircularProgressIndicator(color: Colors.white)
                : const Text('บันทึกข้อมูลสถานที่', style: TextStyle(fontSize: 18, color: Colors.white)),
            ),
          )
        ],
      ),
    );
  }
}