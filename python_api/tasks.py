import multiprocessing

# Force the "spawn" start method for multiprocessing (important for CUDA)
try:
    multiprocessing.set_start_method('spawn', force=True)
except RuntimeError:
    pass

from celery import Celery
import torch
from transformers import LlamaTokenizer, MistralForCausalLM, BitsAndBytesConfig
import os
import redis

# Configure Celery to use Redis as the broker and backend
celery_app = Celery(
    "inference_tasks",
    broker="redis://redis:6379/0",  # Adjust according to your settings
    backend="redis://redis:6379/0"
)

# Load the tokenizer and model once when the worker starts
tokenizer = LlamaTokenizer.from_pretrained(
    "NousResearch/Nous-Hermes-2-Mistral-7B-DPO",
    trust_remote_code=True
)

quantization_config = BitsAndBytesConfig(
    load_in_4bit=True,
    bnb_4bit_quant_type="nf4",
    bnb_4bit_compute_dtype=torch.float16
)

model = MistralForCausalLM.from_pretrained(
    "NousResearch/Nous-Hermes-2-Mistral-7B-DPO",
    torch_dtype=torch.float16,
    device_map="auto",
    quantization_config=quantization_config,
    attn_implementation="flash_attention_2",
    trust_remote_code=True
)

# Create a Redis connection for tracking pending tasks (if needed)
redis_url = os.getenv("REDIS_URL", "redis://redis:6379/0")
r = redis.Redis.from_url(redis_url)

@celery_app.task(bind=True)
def infer(self, conversation: str, prompt: str) -> str:
    # Build the full prompt from conversation context and new input
    full_prompt = conversation + f"<|im_start|>user\n{prompt}\n<|im_end|>\n<|im_start|>assistant\n"
    inputs = tokenizer(full_prompt, return_tensors="pt")
    input_ids = inputs.input_ids.to("cuda")
    attention_mask = inputs.attention_mask.to("cuda") if "attention_mask" in inputs else None

    generated_ids = model.generate(
        input_ids,
        attention_mask=attention_mask,
        max_new_tokens=750,
        temperature=0.8,
        repetition_penalty=1.1,
        do_sample=True,
        eos_token_id=tokenizer.eos_token_id
    )

    # Decode generated tokens, ignoring the original prompt tokens
    response = tokenizer.decode(
        generated_ids[0][input_ids.shape[-1]:],
        skip_special_tokens=True,
        clean_up_tokenization_space=True
    )
    
    # Optionally, remove the task from your queue tracking here.
    # r.lrem("pending_tasks", 0, self.request.id)
    
    return response
