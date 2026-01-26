<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Shakthi Cart | Checkout</title>
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
</head>

<body>

    <button id="payNowBtn">Pay Now</button>

    <script>
        const API_BASE = "http://localhost:8000/api"; // change this
        const AUTH_TOKEN = "Bearer eyJpdiI6Ik9uL0ZsNkdNWWhFN1NNZW5MNTFzaFE9PSIsInZhbHVlIjoiREJXMVNMQlZoRUNkVis3UlJtak5ZNDBpa3hIL0ZkMy9NVnZ4WkppbGw4MzhMbEl2dDVURENmZVF3bG11OW9yZk1TSWhOS1pjdmRPQlN3SFRoWFdHRXNJM0RhTTVnMHcyQnVXcGZ1ZXRJZTg3bGtlN0RIdVZZMUpGWEJVaEg4cjFoMVN5aDRqVjZhMXp4Mm9MQUJEUnRseE5NUnNoRDlrU21JK3BsbFpNOFhibE90NHlhZFJGWjFoQjUrS2dWa3FKIiwibWFjIjoiMmViYmRhOGY5NWNlYzc3NmNkMWM0NzY4NmUzM2IxZTJkZjJkZjYyMmM3NmMyNWQ0OWVkMjUxODgxOWE5NDZkYSIsInRhZyI6IiJ9"; // set after login

        document.getElementById("payNowBtn").addEventListener("click", async () => {
            try {
                // 1️⃣ PLACE ORDER
                const placeOrderRes = await fetch(`${API_BASE}/order/place`, {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "Authorization": AUTH_TOKEN
                    },
                    body: JSON.stringify({
                        user_id: "SAK00001US",
                        payment_mode: "razorpay",
                        address: {
                            building: "Flat 12B",
                            address_line1: "MG Road",
                            address_line2: "Near Metro Station",
                            city: "Bengaluru",
                            district: "Bengaluru Urban",
                            state: "Karnataka",
                            pincode: "560001",
                            address_type: "home"
                        },
                        items: [
                            { product_id: "PRD1", size: "M", quantity: 2 },
                            { product_id: "PRD1", size: "S", quantity: 1 }
                        ]
                    })
                });

                const placeOrderData = await placeOrderRes.json();
                if (!placeOrderData.success) throw placeOrderData.message;

                const orderId = placeOrderData.order_id;

                // 2️⃣ CHECKOUT API
                const checkoutRes = await fetch(`${API_BASE}/checkout`, {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "Authorization": AUTH_TOKEN
                    },
                    body: JSON.stringify({ order_id: orderId })
                });

                const checkoutData = await checkoutRes.json();
                if (!checkoutData.success) throw checkoutData.message;

                const options = {
                    key: checkoutData.checkout.key,
                    amount: checkoutData.checkout.amount,
                    currency: checkoutData.checkout.currency,
                    name: checkoutData.checkout.name,
                    description: checkoutData.checkout.description,
                    order_id: checkoutData.checkout.order_id,

                    prefill: checkoutData.checkout.prefill,
                    notes: checkoutData.checkout.notes,

                    handler: async function (response) {
                        // 3️⃣ VERIFY PAYMENT
                        const verifyRes = await fetch(`${API_BASE}/razorpay/verify-payment`, {
                            method: "POST",
                            headers: {
                                "Content-Type": "application/json",
                                "Authorization": AUTH_TOKEN
                            },
                            body: JSON.stringify({
                                razorpay_order_id: response.razorpay_order_id,
                                razorpay_payment_id: response.razorpay_payment_id,
                                razorpay_signature: response.razorpay_signature
                            })
                        });

                        const verifyData = await verifyRes.json();

                        if (verifyData.success) {
                            window.location.href = verifyData.redirect_url;
                        } else {
                            alert("Payment verification failed");
                        }
                    },

                    modal: {
                        ondismiss: function () {
                            alert("Payment cancelled");
                        }
                    },

                    theme: {
                        color: "#2E7D32"
                    }
                };

                const rzp = new Razorpay(options);
                rzp.open();

            } catch (err) {
                console.error(err);
                alert("Something went wrong");
            }
        });
    </script>

</body>

</html>