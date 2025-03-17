import time
import logging
from fastapi import FastAPI, HTTPException
from pydantic import BaseModel
import torch
from transformers import LlamaTokenizer, MistralForCausalLM, BitsAndBytesConfig

# Configure logging
logging.basicConfig(level=logging.INFO, format="%(asctime)s - %(levelname)s - %(message)s")

app = FastAPI()

# Vérification GPU/CPU
device = "cuda" if torch.cuda.is_available() else "cpu"
logging.info(f"Using device: {device}")

# Charger le tokenizer et le modèle
logging.info("Loading tokenizer...")
tokenizer = LlamaTokenizer.from_pretrained("NousResearch/Nous-Hermes-2-Mistral-7B-DPO", trust_remote_code=True)

logging.info("Loading model...")
quantization_config = BitsAndBytesConfig(
    load_in_4bit=True,
    bnb_4bit_quant_type="nf4",
    bnb_4bit_compute_dtype=torch.float16
)

model = MistralForCausalLM.from_pretrained(
    "NousResearch/Nous-Hermes-2-Mistral-7B-DPO",
    torch_dtype=torch.float16 if device == "cuda" else torch.float32,
    device_map="auto",
    quantization_config=quantization_config,
    attn_implementation="flash_attention_2",
    trust_remote_code=True
).to(device)

logging.info("Model loaded successfully.")

# Définition de la structure de la requête
class ChatRequest(BaseModel):
    conversation: str
    prompt: str

@app.post("/chat")
async def chat(request: ChatRequest):
    start_time = time.time()  # Start time for performance tracking

    try:
        logging.info(f"Received request with prompt: {request.prompt}")

        # Construire le prompt complet
        full_prompt = f"{request.conversation}<|im_start|>user\n{request.prompt}\n<|im_end|>\n<|im_start|>assistant\n"
        
        # Tokenization
        inputs = tokenizer(full_prompt, return_tensors="pt")
        input_ids = inputs.input_ids.to(device)
        attention_mask = inputs.attention_mask.to(device) if "attention_mask" in inputs else None

        logging.info(f"Tokenized input length: {input_ids.shape}")

        # Generate response
        generated_ids = model.generate(
            input_ids,
            attention_mask=attention_mask,
            max_new_tokens=4096,
            temperature=0.8,
            repetition_penalty=1.1,
            do_sample=True,
            eos_token_id=tokenizer.eos_token_id
        )

        # Décoder la réponse en ignorant les tokens du prompt initial
        response = tokenizer.decode(
            generated_ids[0][input_ids.shape[-1]:],
            skip_special_tokens=True,
            clean_up_tokenization_spaces=True
        ).strip()

        elapsed_time = time.time() - start_time
        logging.info(f"Generated response in {elapsed_time:.2f} seconds: {response}")

        return {
            "response": response,
            "debug": {
                "tokenized_length": input_ids.shape[1],
                "execution_time": elapsed_time
            }
        }

    except Exception as e:
        logging.error(f"Error generating response: {str(e)}", exc_info=True)
        raise HTTPException(status_code=500, detail=f"Error generating response: {str(e)}")

