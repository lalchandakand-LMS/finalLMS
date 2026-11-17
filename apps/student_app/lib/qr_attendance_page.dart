import 'dart:async';

import 'package:material_ui/material_ui.dart';
import 'package:geolocator/geolocator.dart';
import 'package:mobile_scanner/mobile_scanner.dart';

import 'api_client.dart';

String validatedAttendanceQrCheckInPath(
  String rawValue, {
  String? apiBaseUrl,
}) {
  final scanned = Uri.tryParse(rawValue);
  final apiBase = Uri.parse(apiBaseUrl ?? MobileApi.baseUrl);
  final apiPath = apiBase.path.endsWith('/')
      ? apiBase.path.substring(0, apiBase.path.length - 1)
      : apiBase.path;
  final requiredPrefix = '$apiPath/attendance/qr/';
  final hasSessionPrefix = scanned?.path.startsWith(requiredPrefix) ?? false;
  final hasCheckInSuffix = scanned?.path.endsWith('/check-in') ?? false;
  final sessionId = hasSessionPrefix && hasCheckInSuffix
      ? scanned!.path.substring(
          requiredPrefix.length,
          scanned.path.length - '/check-in'.length,
        )
      : '';
  final uuidParts = sessionId.split('-');
  const uuidPartLengths = [8, 4, 4, 4, 12];
  final validSessionId = uuidParts.length == uuidPartLengths.length &&
      uuidParts.asMap().entries.every(
        (entry) =>
            entry.value.length == uuidPartLengths[entry.key] &&
            int.tryParse(entry.value, radix: 16) != null,
      );

  if (scanned == null ||
      scanned.scheme != 'https' ||
      scanned.origin != apiBase.origin ||
      !validSessionId ||
      scanned.fragment.isNotEmpty ||
      scanned.queryParameters['nonce']?.isNotEmpty != true ||
      scanned.queryParameters['expires']?.isNotEmpty != true ||
      scanned.queryParameters['signature']?.isNotEmpty != true) {
    throw const ApiException('This is not a valid academy attendance QR code.');
  }

  return '${scanned.path.substring(apiPath.length)}?${scanned.query}';
}

class StudentQrAttendancePage extends StatefulWidget {
  const StudentQrAttendancePage({required this.api, super.key});

  final MobileApi api;

  @override
  State<StudentQrAttendancePage> createState() => _StudentQrAttendancePageState();
}

class _StudentQrAttendancePageState extends State<StudentQrAttendancePage> {
  late final MobileScannerController scanner;
  bool processing = false;
  bool completed = false;
  String? message;
  bool messageIsError = false;

  @override
  void initState() {
    super.initState();
    scanner = MobileScannerController(
      formats: const [BarcodeFormat.qrCode],
      detectionSpeed: DetectionSpeed.noDuplicates,
    );
  }

  @override
  void dispose() {
    unawaited(scanner.dispose());
    super.dispose();
  }

  Future<void> _handleCapture(BarcodeCapture capture) async {
    if (processing || completed || capture.barcodes.isEmpty) return;
    final rawValue = capture.barcodes
        .map((barcode) => barcode.rawValue)
        .whereType<String>()
        .firstWhere((value) => value.isNotEmpty, orElse: () => '');
    if (rawValue.isEmpty) return;

    setState(() {
      processing = true;
      message = 'Checking QR session…';
      messageIsError = false;
    });
    await scanner.stop();

    try {
      final checkInPath = _validatedCheckInPath(rawValue);
      final position = await _foregroundPosition();
      final response = await widget.api.postJson(checkInPath, {
        'latitude': position.latitude,
        'longitude': position.longitude,
        'accuracy_meters': position.accuracy,
        'captured_at': position.timestamp.toIso8601String(),
      });
      final result = response['data'] as Map<String, dynamic>? ?? {};
      final status = result['status']?.toString();
      final duplicate = status == 'duplicate';

      if (!mounted) return;
      setState(() {
        completed = status == 'synced' || duplicate;
        message = duplicate
            ? 'Your attendance was already recorded for this QR session.'
            : completed
                ? 'Attendance recorded successfully.'
                : 'The server did not confirm this attendance. Please contact your instructor.';
        messageIsError = !completed;
      });
    } on ApiException catch (exception) {
      if (!mounted) return;
      setState(() {
        message = exception.statusCode == 410
            ? 'This QR session has expired. Ask your instructor for a new QR code.'
            : exception.statusCode == 403
                ? 'This QR code is invalid or you are not actively enrolled in this batch.'
                : exception.toString();
        messageIsError = true;
      });
    } catch (exception) {
      if (!mounted) return;
      setState(() {
        message = exception.toString();
        messageIsError = true;
      });
    } finally {
      if (mounted) {
        setState(() => processing = false);
        if (!completed) {
          try {
            await scanner.start();
          } catch (_) {
            setState(() {
              message = 'Camera could not restart. Leave this screen and try again.';
              messageIsError = true;
            });
          }
        }
      }
    }
  }

  String _validatedCheckInPath(String rawValue) =>
      validatedAttendanceQrCheckInPath(rawValue);

  Future<Position> _foregroundPosition() async {
    final serviceEnabled = await Geolocator.isLocationServiceEnabled();
    if (!serviceEnabled) {
      throw const ApiException('Turn on device location to check in.');
    }

    var permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.denied) {
      permission = await Geolocator.requestPermission();
    }
    if (permission == LocationPermission.denied ||
        permission == LocationPermission.deniedForever) {
      throw const ApiException('Location permission is required for attendance check-in.');
    }

    return Geolocator.getCurrentPosition(
      locationSettings: const LocationSettings(
        accuracy: LocationAccuracy.high,
        timeLimit: Duration(seconds: 20),
      ),
    );
  }

  Future<void> _scanAgain() async {
    setState(() {
      completed = false;
      message = null;
      messageIsError = false;
    });
    await scanner.start();
  }

  @override
  Widget build(BuildContext context) {
    final color = messageIsError
        ? Theme.of(context).colorScheme.error
        : Theme.of(context).colorScheme.primary;

    return Scaffold(
      appBar: AppBar(title: const Text('QR attendance check-in')),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.all(20),
          children: [
            const Text(
              'Scan your instructor’s current attendance QR code. Camera and location are used only while you are checking in.',
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 18),
            ClipRRect(
              borderRadius: BorderRadius.circular(18),
              child: AspectRatio(
                aspectRatio: 1,
                child: completed
                    ? const ColoredBox(
                        color: Color(0xFF102A43),
                        child: Center(
                          child: Icon(Icons.verified, color: Colors.white, size: 88),
                        ),
                      )
                    : MobileScanner(
                        controller: scanner,
                        onDetect: _handleCapture,
                      ),
              ),
            ),
            const SizedBox(height: 18),
            if (processing)
              const Center(child: CircularProgressIndicator()),
            if (message != null)
              Text(
                message!,
                textAlign: TextAlign.center,
                style: TextStyle(color: color, fontWeight: FontWeight.w600),
              ),
            const SizedBox(height: 12),
            if (completed)
              FilledButton.icon(
                onPressed: () => Navigator.of(context).pop(),
                icon: const Icon(Icons.check),
                label: const Text('Done'),
              )
            else if (messageIsError && !processing)
              OutlinedButton.icon(
                onPressed: _scanAgain,
                icon: const Icon(Icons.refresh),
                label: const Text('Try again'),
              ),
            const SizedBox(height: 8),
            const Text(
              'QR sessions expire within 120 seconds. Screenshots or old QR codes cannot be reused after expiry.',
              textAlign: TextAlign.center,
              style: TextStyle(fontSize: 12),
            ),
          ],
        ),
      ),
    );
  }
}
