import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';
import 'package:http/http.dart' as http;
import 'dart:convert';
import 'package:geolocator/geolocator.dart';
import 'package:url_launcher/url_launcher.dart'; // 🚨 1. นำเข้าแพ็กเกจสำหรับเปิดแอปภายนอก

class AddCompanyMapScreen extends StatefulWidget {
  const AddCompanyMapScreen({Key? key}) : super(key: key);

  @override
  State<AddCompanyMapScreen> createState() => _AddCompanyMapScreenState();
}

class _AddCompanyMapScreenState extends State<AddCompanyMapScreen> {
  final _formKey = GlobalKey<FormState>();
  final _nameController = TextEditingController();
  final _addressController = TextEditingController();
  
  final MapController _mapController = MapController();
  
  // พิกัดเริ่มต้น (กรุงเทพมหานคร ประเทศไทย)
  LatLng _selectedLocation = const LatLng(13.7563, 100.5018);
  bool _isSaving = false;
  bool _isLoadingLocation = false; 

  @override
  void initState() {
    super.initState();
  }

  // 🚨 2. ฟังก์ชันส่งพิกัดไปเปิดใน Google Maps
  Future<void> _openInGoogleMaps() async {
    final double lat = _selectedLocation.latitude;
    final double lng = _selectedLocation.longitude;
    // สร้าง URL ของ Google Maps โดยใส่พิกัดปัจจุบันที่เล็งอยู่
    final String googleMapsUrl = "https://www.google.com/maps/search/?api=1&query=$lat,$lng";
    final Uri uri = Uri.parse(googleMapsUrl);

    if (await canLaunchUrl(uri)) {
      await launchUrl(uri, mode: LaunchMode.externalApplication); // บังคับเปิดแอป Google Maps ถ้ามี
    } else {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('ไม่สามารถเปิด Google Maps ได้'), backgroundColor: Colors.orange),
        );
      }
    }
  }

  Future<void> _getCurrentLocation() async {
    bool serviceEnabled;
    LocationPermission permission;

    serviceEnabled = await Geolocator.isLocationServiceEnabled();
    if (!serviceEnabled) {
      if (mounted) setState(() => _isLoadingLocation = false);
      return;
    }

    permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.denied) {
      permission = await Geolocator.requestPermission();
      if (permission == LocationPermission.denied) {
        if (mounted) setState(() => _isLoadingLocation = false);
        return;
      }
    }
    
    if (permission == LocationPermission.deniedForever) {
      if (mounted) setState(() => _isLoadingLocation = false);
      return;
    } 

    try {
      Position position = await Geolocator.getCurrentPosition(
        desiredAccuracy: LocationAccuracy.high
      );

      if (mounted) {
        setState(() {
          _selectedLocation = LatLng(position.latitude, position.longitude);
          _isLoadingLocation = false;
        });
        _mapController.move(_selectedLocation, 16.0); 
      }
    } catch (e) {
      if (mounted) setState(() => _isLoadingLocation = false);
    }
  }

  void _goToMyLocation() {
    setState(() => _isLoadingLocation = true);
    _getCurrentLocation();
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
  // 🚨 1. ฟังก์ชันเด้ง Popup ยืนยันก่อนยิง API บันทึกข้อมูล
  void _confirmBeforeSave() {
    if (!_formKey.currentState!.validate()) return;

    showDialog(
      context: context,
      builder: (BuildContext dialogContext) {
        return AlertDialog(
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(15)),
          title: const Row(
            children: [
              Icon(Icons.help_outline, color: Colors.indigo),
              SizedBox(width: 10),
              Text('ยืนยันข้อมูล?', style: TextStyle(fontWeight: FontWeight.bold)),
            ],
          ),
          content: const Text(
            'เพื่อป้องกันการปักหมุดคลาดเคลื่อน แนะนำให้กดปุ่ม "เช็คพิกัดบน Map" เพื่อตรวจสอบตำแหน่งจริงบน Google Maps ก่อน\n\n'
            'คุณตรวจสอบเรียบร้อยและต้องการบันทึกสถานที่นี้ใช่หรือไม่?'
          ),
          actions: [
            TextButton(
              onPressed: () {
                Navigator.pop(dialogContext); // ปิด Popup (ยกเลิกการบันทึก)
              },
              child: const Text('ยกเลิก', style: TextStyle(color: Colors.grey, fontWeight: FontWeight.bold)),
            ),

            ElevatedButton(
              style: ElevatedButton.styleFrom(
                backgroundColor: Colors.indigo,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
              ),
              onPressed: () {
                Navigator.pop(dialogContext); // ปิด Popup
                _saveCompany(); // 🚨 ค่อยเรียกฟังก์ชันบันทึกลงฐานข้อมูลของจริง
              },
              child: const Text('ยืนยัน', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
            ),
          ],
        );
      },
    );
  }

  Future<void> _saveCompany() async {
    if (!_formKey.currentState!.validate()) return;
    
    setState(() => _isSaving = true);
    try {
      final response = await http.post(
        Uri.parse('https://student.cet.rmutr.ac.th/coopcet/internship/app/add_company.php'),
        body: {
          'company_name': _nameController.text.trim(),
          'address': _addressController.text.trim(),
          'latitude': _selectedLocation.latitude.toString(),
          'longitude': _selectedLocation.longitude.toString(),
        },
      );
      
      final data = jsonDecode(response.body);
      if (data['success']) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('เพิ่มสถานที่สำเร็จ!'), backgroundColor: Colors.green));
          Navigator.pop(context, data['company_id']); 
        }
      } else {
        _showError(data['message']);
      }
    } catch (e) {
      _showError('เกิดข้อผิดพลาด: $e');
    } finally {
      if (mounted) setState(() => _isSaving = false);
    }
  }

  void _showError(String msg) {
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(msg), backgroundColor: Colors.red));
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('เพิ่มสถานประกอบการใหม่'),
        backgroundColor: Colors.indigo,
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
                    decoration: const InputDecoration(labelText: 'ชื่อสถานประกอบการ', border: OutlineInputBorder(), prefixIcon: Icon(Icons.business)),
                    validator: (val) => val!.isEmpty ? 'กรุณากรอกชื่อบริษัท' : null,
                  ),
                  const SizedBox(height: 12),
                  TextFormField(
                    controller: _addressController,
                    maxLines: 2,
                    decoration: const InputDecoration(labelText: 'ที่อยู่แบบละเอียด', border: OutlineInputBorder(), prefixIcon: Icon(Icons.location_city)),
                    validator: (val) => val!.isEmpty ? 'กรุณากรอกที่อยู่' : null,
                  ),
                  const SizedBox(height: 12),
                  
                  // ส่วนของ Auto-complete สำหรับค้นหาและวางพิกัด
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
                          prefixIcon: const Icon(Icons.search, color: Colors.indigo),
                          suffixIcon: IconButton(
                            icon: const Icon(Icons.clear),
                            onPressed: () {
                              textEditingController.clear();
                            },
                          ),
                        ),
                      );
                    },
                    // ปรับแต่งหน้าตาของ Dropdown ตัวเลือกที่ค้นหาเจอ
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
          
          // 🚨 3. เพิ่มปุ่มกดไปดูใน Google Maps ให้ชัดเจน
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16.0, vertical: 8.0),
            child: Row(
              children: [
                const Icon(Icons.touch_app, color: Colors.indigo),
                const SizedBox(width: 8),
                const Expanded(
                  child: Text('แตะแผนที่เพื่อปักหมุด', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                ),
                OutlinedButton.icon(
                  onPressed: _openInGoogleMaps,
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

          Expanded(
            child: Stack(
              children: [
                FlutterMap(
                  mapController: _mapController, 
                  options: MapOptions(
                    initialCenter: _selectedLocation,
                    initialZoom: 13.0,
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
                
                if (_isLoadingLocation)
                  Container(
                    color: Colors.white.withOpacity(0.5),
                    child: const Center(
                      child: CircularProgressIndicator(color: Colors.indigo),
                    ),
                  ),
              ],
            ),
          ),

          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(16),
            color: Colors.white,
            child: ElevatedButton(
              // 🚨 2. เปลี่ยนจาก _saveCompany เป็น _confirmBeforeSave 
              onPressed: _isSaving ? null : _confirmBeforeSave, 
              style: ElevatedButton.styleFrom(
                backgroundColor: Colors.indigo,
                padding: const EdgeInsets.symmetric(vertical: 15),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10))
              ),
              child: _isSaving 
                ? const CircularProgressIndicator(color: Colors.white)
                : const Text('บันทึกและเลือกสถานที่นี้', style: TextStyle(fontSize: 18, color: Colors.white)),
            ),
          )
        ],
      ),
      
      floatingActionButton: Padding(
        padding: const EdgeInsets.only(bottom: 80.0), 
        child: FloatingActionButton(
          onPressed: _goToMyLocation,
          backgroundColor: Colors.white,
          foregroundColor: Colors.indigo,
          mini: true,
          child: const Icon(Icons.my_location),
        ),
      ),
    );
  }
}