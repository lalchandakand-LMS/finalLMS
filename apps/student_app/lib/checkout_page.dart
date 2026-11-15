import 'package:file_picker/file_picker.dart';
import 'package:material_ui/material_ui.dart';
import 'package:url_launcher/url_launcher.dart';

import 'api_client.dart';

class CheckoutPage extends StatefulWidget {
  const CheckoutPage({required this.api, required this.slug, super.key});

  final MobileApi api;
  final String slug;

  @override
  State<CheckoutPage> createState() => _CheckoutPageState();
}

class _CheckoutPageState extends State<CheckoutPage> {
  final reference = TextEditingController();
  final senderAccount = TextEditingController();
  Map<String, dynamic>? quote;
  List<dynamic> batches = [];
  List<dynamic> methods = [];
  int? selectedBatchId;
  String? selectedMethod;
  PlatformFile? proof;
  bool loading = true;
  bool busy = false;
  String? error;
  String? notice;

  @override
  void initState() {
    super.initState();
    loadQuote();
  }

  @override
  void dispose() {
    reference.dispose();
    senderAccount.dispose();
    super.dispose();
  }

  Future<void> loadQuote() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      final response = await widget.api.checkoutQuote(
        widget.slug,
        batchId: selectedBatchId,
      );
      if (!mounted) return;
      final nextMethods = response['payment_methods'] as List<dynamic>? ?? [];
      setState(() {
        quote = response;
        batches = response['open_batches'] as List<dynamic>? ?? [];
        methods = nextMethods;
        if (selectedMethod == null ||
            !nextMethods.any((item) =>
                (item as Map<String, dynamic>)['key'] == selectedMethod)) {
          selectedMethod = nextMethods
              .where((item) =>
                  (item as Map<String, dynamic>)['key'] != 'bkash')
              .map((item) => (item as Map<String, dynamic>)['key'].toString())
              .firstOrNull;
        }
      });
    } catch (exception) {
      if (mounted) setState(() => error = exception.toString());
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> chooseBatch(int? id) async {
    setState(() {
      selectedBatchId = id;
      quote = null;
      notice = null;
    });
    await loadQuote();
  }

  Future<void> pickProof() async {
    final result = await FilePicker.platform.pickFiles(
      type: FileType.custom,
      allowedExtensions: const ['jpg', 'jpeg', 'png', 'pdf'],
      withData: true,
    );
    if (result == null || result.files.isEmpty) return;
    final file = result.files.single;
    if (file.size > 5 * 1024 * 1024) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Proof file must be 5 MB or smaller.')),
        );
      }
      return;
    }
    if (mounted) setState(() => proof = file);
  }

  Future<void> submitFreeEnrollment() async {
    await _perform(() async {
      final response = await widget.api.postJson(
        '/courses/${Uri.encodeComponent(widget.slug)}/checkout',
        {if (selectedBatchId != null) 'batch_id': selectedBatchId},
      );
      return response['message']?.toString() ?? 'Enrollment completed.';
    });
  }

  Future<void> submitManualPayment() async {
    if (selectedMethod == null) {
      setState(() => error = 'Choose a payment method.');
      return;
    }
    if (reference.text.trim().isEmpty) {
      setState(() => error = 'Enter the transaction reference.');
      return;
    }
    await _perform(() async {
      final response = await widget.api.submitManualCheckout(
        widget.slug,
        batchId: selectedBatchId,
        paymentMethod: selectedMethod!,
        transactionReference: reference.text.trim(),
        senderAccount: senderAccount.text,
        proof: proof,
      );
      return response['message']?.toString() ??
          'Payment submitted for academy verification.';
    });
  }

  Future<void> startBkash() async {
    await _perform(() async {
      final response = await widget.api.startBkashCheckout(
        widget.slug,
        batchId: selectedBatchId,
      );
      final rawUrl = response['checkout_url']?.toString();
      final uri = rawUrl == null ? null : Uri.tryParse(rawUrl);
      const allowedHosts = {
        'tokenized.sandbox.bka.sh',
        'tokenized.pay.bka.sh',
        'checkout.sandbox.bka.sh',
        'checkout.pay.bka.sh',
      };
      if (uri == null ||
          uri.scheme != 'https' ||
          !allowedHosts.contains(uri.host)) {
        throw const FormatException('The payment provider returned an invalid checkout link.');
      }
      final opened = await launchUrl(uri, mode: LaunchMode.inAppBrowserView);
      if (!opened) {
        throw const FormatException('Could not open the secure bKash checkout page.');
      }
      return 'bKash checkout opened. Your enrollment will update only after the server verifies payment; returning to this app is not proof of payment.';
    });
  }

  Future<void> _perform(Future<String> Function() action) async {
    setState(() {
      busy = true;
      error = null;
      notice = null;
    });
    try {
      final message = await action();
      if (!mounted) return;
      setState(() => notice = message);
      await loadQuote();
    } catch (exception) {
      if (mounted) setState(() => error = exception.toString());
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  Widget _statusCard(String title, String message, IconData icon) => Card(
        child: ListTile(
          leading: Icon(icon),
          title: Text(title),
          subtitle: Text(message),
        ),
      );

  @override
  Widget build(BuildContext context) {
    final data = quote;
    final course = data?['course'] as Map<String, dynamic>?;
    final hasBatches = batches.isNotEmpty;
    final batchRequired = data?['batch_required'] == true;
    final alreadyEnrolled = data?['already_enrolled'] == true;
    final paymentPending = data?['payment_pending'] == true;
    final isFree = data?['is_free'] == true && !batchRequired;
    final manualMethods = methods
        .where((item) => (item as Map<String, dynamic>)['key'] != 'bkash')
        .map((item) => item as Map<String, dynamic>)
        .toList();
    final bkashEnabled = methods.any(
      (item) => (item as Map<String, dynamic>)['key'] == 'bkash',
    );

    return Scaffold(
      appBar: AppBar(title: const Text('Course checkout')),
      body: loading && data == null
          ? const Center(child: CircularProgressIndicator())
          : error != null && data == null
              ? Center(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(error!),
                      TextButton(onPressed: loadQuote, child: const Text('Retry')),
                    ],
                  ),
                )
              : RefreshIndicator(
                  onRefresh: loadQuote,
                  child: ListView(
                    padding: const EdgeInsets.all(16),
                    children: [
                      Text(
                        course?['title']?.toString() ?? 'Course',
                        style: Theme.of(context).textTheme.headlineSmall,
                      ),
                      const SizedBox(height: 16),
                      if (hasBatches) ...[
                        Text('Choose a batch', style: Theme.of(context).textTheme.titleMedium),
                        const SizedBox(height: 8),
                        DropdownButtonFormField<int>(
                          value: selectedBatchId,
                          decoration: const InputDecoration(
                            labelText: 'Available batch',
                            border: OutlineInputBorder(),
                          ),
                          items: batches.map((raw) {
                            final item = raw as Map<String, dynamic>;
                            final fee = (item['fee'] as num?)?.toDouble() ?? 0;
                            final title = item['title']?.toString() ?? 'Batch';
                            final start = item['starts_at']?.toString();
                            return DropdownMenuItem<int>(
                              value: (item['id'] as num).toInt(),
                              child: Text(
                                '$title • ৳${fee.toStringAsFixed(2)}${start == null ? '' : ' • $start'}',
                                overflow: TextOverflow.ellipsis,
                              ),
                            );
                          }).toList(),
                          onChanged: busy ? null : chooseBatch,
                        ),
                        const SizedBox(height: 16),
                      ],
                      if (batchRequired)
                        _statusCard(
                          'Batch selection required',
                          'Choose an available batch to see the correct price and continue.',
                          Icons.event_available,
                        )
                      else if (data != null) ...[
                        Card(
                          child: Padding(
                            padding: const EdgeInsets.all(16),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                const Text('Amount due'),
                                const SizedBox(height: 6),
                                Text(
                                  '৳${((data['discounted_amount'] as num?)?.toDouble() ?? 0).toStringAsFixed(2)}',
                                  style: Theme.of(context).textTheme.headlineMedium,
                                ),
                                if (data['flash_sale_applied'] == true)
                                  const Chip(label: Text('Flash sale price applied')),
                                if (data['base_amount'] != data['discounted_amount'])
                                  Text('Original price: ৳${data['base_amount']}'),
                              ],
                            ),
                          ),
                        ),
                        if (alreadyEnrolled)
                          _statusCard(
                            'Already enrolled',
                            'This course is already available in My Courses.',
                            Icons.check_circle_outline,
                          )
                        else if (paymentPending)
                          _statusCard(
                            'Payment awaiting verification',
                            'The academy has not approved this payment yet. Course access is not granted until verification.',
                            Icons.hourglass_top,
                          )
                        else if (isFree) ...[
                          const Text('This course is free. Confirm to activate your enrollment.'),
                          const SizedBox(height: 12),
                          FilledButton.icon(
                            onPressed: busy ? null : submitFreeEnrollment,
                            icon: const Icon(Icons.school_outlined),
                            label: busy
                                ? const CircularProgressIndicator()
                                : const Text('Enroll for free'),
                          ),
                        ] else ...[
                          if (manualMethods.isNotEmpty) ...[
                            DropdownButtonFormField<String>(
                              value: selectedMethod,
                              decoration: const InputDecoration(
                                labelText: 'Manual payment method',
                                border: OutlineInputBorder(),
                              ),
                              items: manualMethods.map((method) {
                                return DropdownMenuItem<String>(
                                  value: method['key'].toString(),
                                  child: Text(method['label']?.toString() ?? method['key'].toString()),
                                );
                              }).toList(),
                              onChanged: busy ? null : (value) => setState(() => selectedMethod = value),
                            ),
                            if (selectedMethod != null)
                              Builder(builder: (context) {
                                final method = manualMethods.firstWhere(
                                  (item) => item['key'] == selectedMethod,
                                );
                                final instructions = method['instructions']?.toString() ?? '';
                                final account = method['account']?.toString() ?? '';
                                return Padding(
                                  padding: const EdgeInsets.symmetric(vertical: 12),
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      if (account.isNotEmpty) SelectableText('Send payment to: $account'),
                                      if (instructions.isNotEmpty) Text(instructions),
                                    ],
                                  ),
                                );
                              }),
                            TextField(
                              controller: senderAccount,
                              keyboardType: TextInputType.phone,
                              decoration: const InputDecoration(
                                labelText: 'Your sender account (optional)',
                                border: OutlineInputBorder(),
                              ),
                            ),
                            const SizedBox(height: 12),
                            TextField(
                              controller: reference,
                              decoration: const InputDecoration(
                                labelText: 'Transaction reference',
                                border: OutlineInputBorder(),
                              ),
                            ),
                            const SizedBox(height: 8),
                            OutlinedButton.icon(
                              onPressed: busy ? null : pickProof,
                              icon: const Icon(Icons.attach_file),
                              label: Text(proof?.name ?? 'Attach proof (JPG, PNG or PDF • max 5 MB)'),
                            ),
                            if (proof != null)
                              TextButton.icon(
                                onPressed: busy ? null : () => setState(() => proof = null),
                                icon: const Icon(Icons.close),
                                label: const Text('Remove proof'),
                              ),
                            const SizedBox(height: 8),
                            FilledButton.icon(
                              onPressed: busy ? null : submitManualPayment,
                              icon: const Icon(Icons.upload_file),
                              label: busy
                                  ? const CircularProgressIndicator()
                                  : const Text('Submit payment for review'),
                            ),
                          ],
                          if (bkashEnabled) ...[
                            const Divider(height: 32),
                            FilledButton.icon(
                              onPressed: busy ? null : startBkash,
                              icon: const Icon(Icons.account_balance_wallet_outlined),
                              label: const Text('Pay securely with bKash'),
                            ),
                            const Text(
                              'You will complete payment on the secure bKash page. The server verifies the payment before granting access.',
                              textAlign: TextAlign.center,
                            ),
                          ],
                          if (manualMethods.isEmpty && !bkashEnabled)
                            _statusCard(
                              'No payment methods available',
                              'Please contact the academy for payment instructions.',
                              Icons.info_outline,
                            ),
                        ],
                      ],
                      if (notice != null) ...[
                        const SizedBox(height: 12),
                        _statusCard('Update', notice!, Icons.info_outline),
                      ],
                      if (error != null) ...[
                        const SizedBox(height: 12),
                        Text(error!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
                      ],
                      const SizedBox(height: 24),
                      const Text(
                        'Payment amounts and enrollment status are verified by the academy server. A manual payment remains pending until approved.',
                        textAlign: TextAlign.center,
                      ),
                    ],
                  ),
                ),
    );
  }
}
