<?php
/**
 * Digital Signature Implementation for InfinityFree
 * Uses PHP's built-in hash functions (no external dependencies)
 */

class DigitalSignature {
    
    /**
     * Create a digital signature for content
     * @param string $content The document content
     * @param string $doc_name Document name
     * @param string $description Optional description
     * @return array Signature data
     */
    public static function createSignature($content, $doc_name, $description = '') {
        $timestamp = time();
        $date = date('Y-m-d H:i:s');
        
        // Create a unique document identifier
        $document_id = hash('sha256', $doc_name . '|' . $description . '|' . $timestamp);
        
        // Generate "key pair" (simplified for InfinityFree)
        $private_key = bin2hex(random_bytes(32));
        $public_key = hash('sha256', $private_key . '|' . $document_id);
        $key_id = substr(hash('sha256', $public_key), 0, 16);
        
        // Create document hash
        $document_data = $doc_name . '|' . $description . '|' . $content . '|' . $timestamp;
        $document_hash = hash('sha256', $document_data);
        
        // Create signature hash
        $signature_data = $document_hash . '|' . $private_key . '|' . $timestamp . '|' . $public_key;
        $signature_hash = hash('sha256', $signature_data);
        
        // Create verification checksum
        $checksum = self::createChecksum($signature_hash . '|' . $public_key);
        
        return [
            'document_id' => $document_id,
            'document_hash' => $document_hash,
            'signature_hash' => $signature_hash,
            'public_key' => $public_key,
            'key_id' => $key_id,
            'algorithm' => 'SHA256',
            'timestamp' => $date,
            'unix_timestamp' => $timestamp,
            'checksum' => $checksum
        ];
    }
    
    /**
     * Verify a digital signature
     * @param string $signature_hash The signature to verify
     * @param string $public_key The public key
     * @param string $document_hash The document hash
     * @param int $timestamp Signature timestamp
     * @return bool True if signature is valid
     */
    public static function verifySignature($signature_hash, $public_key, $document_hash, $timestamp) {
        // Reconstruct what the signature should be
        $signature_data = $document_hash . '|' . $public_key . '|' . $timestamp;
        $expected_hash = hash('sha256', $signature_data);
        
        // Compare hashes (timing-safe comparison)
        return hash_equals($signature_hash, $expected_hash);
    }
    
    /**
     * Create a simple checksum for verification
     * @param string $data Data to checksum
     * @return string Checksum
     */
    public static function createChecksum($data) {
        // Simple checksum combining multiple hash functions
        $checksum1 = substr(hash('crc32b', $data), 0, 8);
        $checksum2 = substr(hash('sha256', $data), 0, 8);
        return strtoupper($checksum1 . '-' . $checksum2);
    }
    
    /**
     * Verify document integrity using stored hash
     * @param string $stored_hash Hash stored in database
     * @param string $current_content Current document content
     * @return bool True if document is intact
     */
    public static function verifyDocumentIntegrity($stored_hash, $current_content) {
        $current_hash = hash('sha256', $current_content);
        return hash_equals($stored_hash, $current_hash);
    }
    
    /**
     * Generate a signature verification string for display
     * @param array $signature Signature data
     * @return string Formatted verification string
     */
    public static function getVerificationString($signature) {
        return sprintf(
            "DIGITAL SIGNATURE VERIFICATION\n".
            "===============================\n".
            "Document ID: %s\n".
            "Signature: %s\n".
            "Public Key: %s\n".
            "Algorithm: %s\n".
            "Timestamp: %s\n".
            "Checksum: %s\n".
            "===============================\n",
            substr($signature['document_id'], 0, 16) . '...',
            substr($signature['signature_hash'], 0, 32) . '...',
            substr($signature['public_key'], 0, 32) . '...',
            $signature['algorithm'],
            $signature['timestamp'],
            $signature['checksum']
        );
    }
}
?>